<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Transmits an event's reinforcement request ("demande de renfort") to other
 * sections by email, and keeps a log of what was sent.
 *
 * Recipients in a target section are the members able to answer with a
 * reinforcement sub-event — those holding permission 15 (create/manage
 * activities) in that section. When nobody qualifies, the section's
 * responsables (`section_role`) are used instead. Delivery goes through the
 * central {@see NotificationService} (queued, honours `mail_allowed`).
 */
class ReinforcementTransmissionService
{
    /** Permission able to create/attach the reinforcement sub-event. */
    private const RESPONDER_PERMISSION = 15;

    /** Delivery channel recorded on each transmission (sms / in_app may follow). */
    public const CHANNEL_EMAIL = 'email';

    public function __construct(private readonly NotificationService $notifications) {}

    /**
     * Sections a request can be sent to: every active section except the
     * event's own.
     *
     * @return Collection<int, stdClass>
     */
    public function targetSections(int $eventSectionId): Collection
    {
        return DB::table('section')
            ->where('S_INACTIVE', 0)
            ->where('S_ID', '<>', $eventSectionId)
            ->where('S_ID', '>=', 0)
            ->orderBy('S_CODE')
            ->get(['S_ID', 'S_CODE', 'S_DESCRIPTION']);
    }

    /**
     * People in a section who should receive the request (with an email).
     *
     * @return Collection<int, stdClass> P_ID, P_NOM, P_PRENOM, P_EMAIL
     */
    public function recipients(int $sectionId): Collection
    {
        $members = DB::table('pompier as p')
            ->where('p.P_OLD_MEMBER', 0)
            ->whereNull('p.P_FIN')
            ->where('p.P_EMAIL', '<>', '')
            ->whereNotNull('p.P_EMAIL')
            ->where(function ($q) use ($sectionId) {
                $q->where('p.P_SECTION', $sectionId)
                    ->orWhereExists(function ($sub) use ($sectionId) {
                        $sub->from('ob_personnel_section as ps')
                            ->whereColumn('ps.person_id', 'p.P_ID')
                            ->where('ps.section_id', $sectionId);
                    });
            })
            ->orderBy('p.P_NOM')
            ->get(['p.P_ID', 'p.P_NOM', 'p.P_PRENOM', 'p.P_EMAIL']);

        $users = User::whereIn('P_ID', $members->pluck('P_ID'))->get()->keyBy('P_ID');
        $responders = $members->filter(
            fn ($m) => $users->has($m->P_ID)
                && $users[$m->P_ID]->hasPermissionInSection(self::RESPONDER_PERMISSION, $sectionId)
        )->values();

        if ($responders->isNotEmpty()) {
            return $responders;
        }

        // Fallback: the section's declared responsables.
        return DB::table('section_role as sr')
            ->join('pompier as p', 'sr.P_ID', '=', 'p.P_ID')
            ->where('sr.S_ID', $sectionId)
            ->where('p.P_EMAIL', '<>', '')
            ->whereNotNull('p.P_EMAIL')
            ->distinct()
            ->orderBy('p.P_NOM')
            ->get(['p.P_ID', 'p.P_NOM', 'p.P_PRENOM', 'p.P_EMAIL']);
    }

    /**
     * Send the request to each section and log it.
     *
     * @param  array<int, int>  $sectionIds
     * @return array<int, int> section id => number of emails handed to the mailer
     */
    public function transmit(object $event, array $sectionIds, ?string $note, User $sender): array
    {
        $allowed = $this->targetSections((int) $event->S_ID)->pluck('S_ID')->map(fn ($id) => (int) $id)->all();
        $subject = __('event.renfort_tx_mail_subject', ['event' => $event->E_LIBELLE ?: $event->E_CODE]);
        $body = $this->body($event, $note, $sender);

        $sent = [];
        foreach (array_unique(array_map('intval', $sectionIds)) as $sid) {
            if (! in_array($sid, $allowed, true)) {
                continue;
            }

            $count = 0;
            foreach ($this->recipients($sid) as $r) {
                if ($this->notifications->sendEmail($r->P_EMAIL, $subject, $body)) {
                    $count++;
                }
            }

            DB::table('ob_renfort_transmission')->insert([
                'event_code' => (int) $event->E_CODE,
                'section_id' => $sid,
                'sent_by' => (int) $sender->P_ID,
                'channel' => self::CHANNEL_EMAIL,
                'recipients' => $count,
                'message' => $note !== null && $note !== '' ? $note : null,
                'sent_at' => now(),
            ]);

            $sent[$sid] = $count;
        }

        return $sent;
    }

    /**
     * Previous transmissions of an event, newest first.
     *
     * @return Collection<int, stdClass>
     */
    public function history(int $eventCode): Collection
    {
        return DB::table('ob_renfort_transmission as t')
            ->leftJoin('section as s', 't.section_id', '=', 's.S_ID')
            ->leftJoin('pompier as p', 't.sent_by', '=', 'p.P_ID')
            ->where('t.event_code', $eventCode)
            ->orderByDesc('t.sent_at')
            ->get(['t.sent_at', 't.channel', 't.recipients', 't.message', 's.S_CODE', 'p.P_NOM', 'p.P_PRENOM']);
    }

    /** Plain-text email body summarising the request. */
    private function body(object $event, ?string $note, User $sender): string
    {
        $code = (int) $event->E_CODE;

        $first = DB::table('evenement_horaire')->where('E_CODE', $code)
            ->orderBy('EH_DATE_DEBUT')->orderBy('EH_DEBUT')
            ->first(['EH_DATE_DEBUT', 'EH_DEBUT', 'EH_DATE_FIN', 'EH_FIN']);
        $section = DB::table('section')->where('S_ID', $event->S_ID)->value('S_CODE');

        $global = DB::table('demande_renfort_vehicule')
            ->where('E_CODE', $code)->where('TV_CODE', '0')
            ->first(['NB_VEHICULES', 'POINT_REGROUPEMENT', 'DEMANDE_SPECIFIQUE']);

        $vehicles = DB::table('demande_renfort_vehicule as d')
            ->leftJoin('type_vehicule as tv', 'd.TV_CODE', '=', 'tv.TV_CODE')
            ->where('d.E_CODE', $code)->where('d.TV_CODE', '<>', '0')
            ->get(['d.TV_CODE', 'd.NB_VEHICULES', 'tv.TV_LIBELLE']);

        $materials = DB::table('demande_renfort_materiel')
            ->where('E_CODE', $code)->pluck('TYPE_MATERIEL');

        $lines = [];
        $lines[] = __('event.renfort_tx_mail_intro', [
            'sender' => strtoupper((string) $sender->P_NOM).' '.$sender->P_PRENOM,
            'section' => $section ?? '—',
        ]);
        $lines[] = '';
        $lines[] = __('event.renfort_tx_mail_event').' : '.($event->E_LIBELLE ?: $event->E_CODE);
        if ($first) {
            $lines[] = __('event.renfort_tx_mail_when').' : '
                .Carbon::parse($first->EH_DATE_DEBUT)->format('d/m/Y')
                .($first->EH_DEBUT ? ' '.substr((string) $first->EH_DEBUT, 0, 5) : '');
        }
        if (! empty($event->E_LIEU)) {
            $lines[] = __('event.renfort_tx_mail_where').' : '.$event->E_LIEU;
        }

        if ($global && (int) $global->NB_VEHICULES > 0) {
            $lines[] = __('event.renfort_tx_mail_nb_vehicles').' : '.(int) $global->NB_VEHICULES;
        }
        foreach ($vehicles as $v) {
            $lines[] = '  - '.(int) $v->NB_VEHICULES.' × '.$v->TV_CODE.($v->TV_LIBELLE ? ' ('.$v->TV_LIBELLE.')' : '');
        }
        if ($materials->isNotEmpty()) {
            $lines[] = __('event.renfort_tx_mail_materials').' : '.$materials->implode(', ');
        }
        if ($global && $global->POINT_REGROUPEMENT) {
            $lines[] = __('event.renfort_tx_mail_point').' : '.$global->POINT_REGROUPEMENT;
        }
        if ($global && $global->DEMANDE_SPECIFIQUE) {
            $lines[] = '';
            $lines[] = __('event.renfort_tx_mail_specific').' :';
            $lines[] = $global->DEMANDE_SPECIFIQUE;
        }
        if ($note !== null && $note !== '') {
            $lines[] = '';
            $lines[] = __('event.renfort_tx_mail_note').' :';
            $lines[] = $note;
        }

        $lines[] = '';
        $lines[] = __('event.renfort_tx_mail_cta');
        $lines[] = route('event.show', $code);

        return implode("\n", $lines);
    }
}
