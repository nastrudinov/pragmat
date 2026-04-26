<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\UserAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class NotificationController extends Controller
{
    /**
     * Получить список уведомлений пользователя
     */
    public function index(Request $request)
    {
        try {
            $user = $this->getAuthenticatedUser();
            if (!$user) {
                return response()->json([
                    'error' => 'User not authenticated'
                ], 401);
            }
            
            $query = Notification::where('user_account_id', $user->id);
            
            // Фильтр по статусу прочтения
            if ($request->has('is_read')) {
                $query->where('is_read', $request->boolean('is_read'));
            }
            
            // Фильтр по типу
            if ($request->has('type')) {
                $query->where('type', $request->type);
            }
            
            // Сортировка
            $sortField = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortField, $sortDirection);
            
            // Пагинация
            $page = $request->get('page', 1);
            $limit = $request->get('limit', 20);
            $total = $query->count();
            
            $notifications = $query->skip(($page - 1) * $limit)
                                   ->take($limit)
                                   ->get()
                                   ->map(function($notification) {
                                       return [
                                           'id' => $notification->id,
                                           'title' => $notification->title,
                                           'message' => $notification->message,
                                           'type' => $notification->type,
                                           'is_read' => $notification->is_read,
                                           'created_at' => $notification->created_at->toISOString(),
                                           'read_at' => $notification->read_at?->toISOString()
                                       ];
                                   });
            
            return response()->json([
                'notifications' => $notifications,
                'total' => $total,
                'page' => $page,
                'limit' => $limit
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch notifications',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Отметить уведомление как прочитанное
     */
    public function markAsRead($id)
    {
        try {
            $user = $this->getAuthenticatedUser();
            if (!$user) {
                return response()->json([
                    'error' => 'User not authenticated'
                ], 401);
            }
            
            $notification = Notification::where('user_account_id', $user->id)
                ->where('id', $id)
                ->firstOrFail();
            
            if (!$notification->is_read) {
                $notification->is_read = true;
                $notification->read_at = now();
                $notification->save();
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read'
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark notification as read',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 8.3 PUT /notifications/mark-all-read - Отметить все как прочитанные
     */
    public function markAllAsRead()
    {
        try {
            $user = $this->getAuthenticatedUser();
            if (!$user) {
                return response()->json([
                    'error' => 'User not authenticated'
                ], 401);
            }
            
            $count = Notification::where('user_account_id', $user->id)
                ->where('is_read', false)
                ->update([
                    'is_read' => true,
                    'read_at' => now()
                ]);
            
            return response()->json([
                'success' => true,
                'count' => $count
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark notifications as read',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 8.4 GET /notifications/unread-count - Количество непрочитанных
     */
    public function getUnreadCount()
    {
        try {
            $user = $this->getAuthenticatedUser();
            if (!$user) {
                return response()->json([
                    'error' => 'User not authenticated'
                ], 401);
            }
            
            $unreadCount = Notification::where('user_account_id', $user->id)
                ->where('is_read', false)
                ->count();
            
            return response()->json([
                'unreadCount' => $unreadCount
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to get unread count',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Создать уведомление (вспомогательный метод)
     */
    public function createNotification($userId, $title, $message, $type = null)
    {
        return Notification::create([
            'user_account_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'is_read' => false
        ]);
    }
    
    /**
     * Получить аутентифицированного пользователя
     */
    private function getAuthenticatedUser()
    {
        // Здесь должна быть логика получения текущего пользователя
        // Например, через Laravel Sanctum или другой механизм аутентификации
        return auth()->user();
    }
}