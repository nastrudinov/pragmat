<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class UpdateEmployeeCourses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trainings:update-statuses';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update statuses of employee courses (expired, expiring, active)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        \Log::info("------------------------------------");
        $message = "Starting update of employee courses statuses at " . now();
        $this->info($message);
        \Log::info($message);
        
        $now = Carbon::now();
        $today = $now->toDateString();
        $thirtyDaysLater = $now->addDays(30)->toDateString();
        
        // 1. Обновляем статус на expired (дата истечения меньше текущей даты)
        $expiredCount = DB::table('employee_courses')
            ->whereNotNull('expiration_date')
            ->where('expiration_date', '<', $today)
            ->where('status', '!=', 'expired')
            ->update([
                'status' => 'expired',
                'updated_at' => now()
            ]);
        
        $this->info("Updated expired courses: {$expiredCount}");
        \Log::info("Updated expired courses: {$expiredCount}");
        
        // 2. Обновляем статус на expiring (дата истечения между сегодня и 30 днями)
        $expiringCount = DB::table('employee_courses')
            ->whereNotNull('expiration_date')
            ->where('expiration_date', '>=', $today)
            ->where('expiration_date', '<=', $thirtyDaysLater)
            ->where('status', '!=', 'expiring')
            ->update([
                'status' => 'expiring',
                'updated_at' => now()
            ]);
        
        $this->info("Updated expiring courses: {$expiringCount}");
        \Log::info("Updated expiring courses: {$expiringCount}");
        
        // 3. Обновляем статус на active (дата истечения больше 30 дней)
        $activeCount = DB::table('employee_courses')
            ->whereNotNull('expiration_date')
            ->where('expiration_date', '>', $thirtyDaysLater)
            ->where('status', '!=', 'active')
            ->update([
                'status' => 'active',
                'updated_at' => now()
            ]);
        
        $this->info("Updated active courses: {$activeCount}");
        \Log::info("Updated active courses: {$activeCount}");
        
        $total = $expiredCount + $expiringCount + $activeCount;
        
        $this->info("Total updated courses: {$total}");
        \Log::info("Total updated courses: {$total}");
        
        $completed = 'Employee courses statuses update completed!';
        $this->info($completed);
        \Log::info($completed);
        
        return Command::SUCCESS;
    }
}