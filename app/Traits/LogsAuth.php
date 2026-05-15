<?php

namespace App\Traits;

use App\Models\AuthLog;
use App\Models\UserAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

trait LogsAuth
{
    /**
     * Сохранить лог авторизации
     */
    protected function logAuth(
        $event,
        $status,
        $username = null,
        $userId = null,
        $employeeId = null,
        $message = null,
        $details = null
    ) {
        try {
            $request = request();
            
            // Определяем устройство и браузер
            $userAgent = $request->userAgent();
            $deviceInfo = $this->parseUserAgent($userAgent);
            
            AuthLog::create([
                'user_id' => $userId,
                'employee_id' => $employeeId,
                'username' => $username,
                'event' => $event,
                'status' => $status,
                'ip_address' => $request->ip(),
                'user_agent' => $userAgent,
                'device_type' => $deviceInfo['device_type'],
                'browser' => $deviceInfo['browser'],
                'os' => $deviceInfo['os'],
                'message' => $message,
                'details' => $details,
                'created_at' => now()
            ]);
        } catch (\Exception $e) {
            // Логируем ошибку в файл, но не прерываем основной процесс
            Log::error('Failed to save auth log: ' . $e->getMessage());
        }
    }
    
    /**
     * Парсинг User Agent
     */
    protected function parseUserAgent($userAgent)
    {
        $result = [
            'device_type' => 'desktop',
            'browser' => 'unknown',
            'os' => 'unknown'
        ];
        
        if (!$userAgent) {
            return $result;
        }
        
        // Определение устройства
        if (preg_match('/(iPhone|iPad|iPod)/i', $userAgent)) {
            $result['device_type'] = 'mobile';
        } elseif (preg_match('/Android/i', $userAgent)) {
            $result['device_type'] = 'mobile';
        } elseif (preg_match('/iPad/i', $userAgent)) {
            $result['device_type'] = 'tablet';
        } elseif (preg_match('/Mobile/i', $userAgent)) {
            $result['device_type'] = 'mobile';
        }
        
        // Определение браузера
        if (preg_match('/Chrome/i', $userAgent) && !preg_match('/Edg/i', $userAgent)) {
            $result['browser'] = 'Chrome';
        } elseif (preg_match('/Firefox/i', $userAgent)) {
            $result['browser'] = 'Firefox';
        } elseif (preg_match('/Safari/i', $userAgent) && !preg_match('/Chrome/i', $userAgent)) {
            $result['browser'] = 'Safari';
        } elseif (preg_match('/Edge/i', $userAgent)) {
            $result['browser'] = 'Edge';
        } elseif (preg_match('/Opera/i', $userAgent)) {
            $result['browser'] = 'Opera';
        } elseif (preg_match('/MSIE/i', $userAgent) || preg_match('/Trident/i', $userAgent)) {
            $result['browser'] = 'Internet Explorer';
        }
        
        // Определение ОС
        if (preg_match('/Windows/i', $userAgent)) {
            $result['os'] = 'Windows';
        } elseif (preg_match('/Mac/i', $userAgent)) {
            $result['os'] = 'macOS';
        } elseif (preg_match('/Linux/i', $userAgent)) {
            $result['os'] = 'Linux';
        } elseif (preg_match('/Android/i', $userAgent)) {
            $result['os'] = 'Android';
        } elseif (preg_match('/iOS/i', $userAgent) || preg_match('/iPhone/i', $userAgent)) {
            $result['os'] = 'iOS';
        }
        
        return $result;
    }
}