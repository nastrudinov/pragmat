<?php

namespace App\Console\Commands;

use App\Models\TrainingEvent;
use Illuminate\Console\Command;

class CheckExpiredEvents extends Command
{
    protected $signature = 'events:check-expired';
    protected $description = 'Check expired events and update status to awaiting_confirmation';

    public function handle()
    {
        $count = TrainingEvent::where('end_date', '<', now())
            ->where('status', '!=', 'awaiting_confirmation')
            ->update(['status' => 'awaiting_confirmation']);

        $this->info("Updated {$count} events to status 'awaiting_confirmation'");
    }
}