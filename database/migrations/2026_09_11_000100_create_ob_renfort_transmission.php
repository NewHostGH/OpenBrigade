<?php

// project: OpenBrigade

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Log of reinforcement requests ("demandes de renfort") transmitted from an
 * event to another section: which section, when, by whom, through which
 * channel, how many recipients were notified and the sender's note. Lets the
 * event show what was sent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ob_renfort_transmission')) {
            return;
        }

        Schema::create('ob_renfort_transmission', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('event_code')->index();
            $table->integer('section_id');
            $table->integer('sent_by')->nullable();
            // How the request was delivered: email today; sms / in_app later.
            $table->string('channel', 20)->default('email');
            $table->unsignedSmallInteger('recipients')->default(0);
            $table->string('message', 600)->nullable();
            $table->timestamp('sent_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ob_renfort_transmission');
    }
};
