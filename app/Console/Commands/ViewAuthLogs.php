<?php

namespace App\Console\Commands;

use App\Models\AuthLog;
use Illuminate\Console\Command;

class ViewAuthLogs extends Command
{
    protected $signature = 'auth:logs {--limit=50 : Количество записей} {--event= : Фильтр по событию} {--status= : Фильтр по статусу}';
    protected $description = 'Просмотр логов авторизации';

    public function handle()
    {
        $query = AuthLog::with(['user', 'employee']);
        
        if ($event = $this->option('event')) {
            $query->where('event', $event);
        }
        
        if ($status = $this->option('status')) {
            $query->where('status', $status);
        }
        
        $logs = $query->orderBy('created_at', 'desc')
            ->limit($this->option('limit'))
            ->get();
        
        $headers = ['ID', 'Username', 'Event', 'Status', 'IP', 'Device', 'Message', 'Created At'];
        
        $rows = $logs->map(function($log) {
            return [
                $log->id,
                $log->username,
                $log->event,
                $log->status,
                $log->ip_address,
                $log->device_type . ' / ' . $log->browser,
                substr($log->message, 0, 50),
                $log->created_at->format('Y-m-d H:i:s')
            ];
        });
        
        $this->table($headers, $rows);
        $this->info('Total: ' . $logs->count());
    }
}