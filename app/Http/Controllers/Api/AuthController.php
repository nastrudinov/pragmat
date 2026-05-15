<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserAccount;
use App\Models\Employee;
use App\Traits\LogsAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    use LogsAuth;
    
    /**
     * POST /auth/login - Вход в систему
     */
    public function login(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'username' => 'required|string',
                'password' => 'required|string'
            ]);

            if ($validator->fails()) {
                $this->logAuth(
                    'login',
                    'failed',
                    $request->username,
                    null,
                    null,
                    'Validation failed',
                    ['errors' => $validator->errors()->toArray()]
                );
                
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Поиск пользователя
            $user = UserAccount::with('employee')
                ->where('username', $request->username)
                ->where('status', 'active')
                ->first();

            if (!$user || !Hash::check($request->password, $user->password_hash)) {
                $this->logAuth(
                    'login',
                    'failed',
                    $request->username,
                    null,
                    null,
                    'Invalid credentials'
                );
                
                return response()->json([
                    'success' => false,
                    'message' => 'Неверное имя пользователя или пароль'
                ], 401);
            }
            
            // Проверка статуса пользователя
            if ($user->status !== 'active') {
                $this->logAuth(
                    'login',
                    'failed',
                    $user->username,
                    $user->id,
                    $user->employee_id,
                    'Account is inactive'
                );
                
                return response()->json([
                    'success' => false,
                    'message' => 'Учетная запись заблокирована'
                ], 403);
            }

            // Обновляем время последнего входа
            $user->last_login = now();
            $user->save();

            // Создаем токен
            $token = $user->createToken('auth_token')->plainTextToken;

            // Формируем ФИО
            $fullName = $this->getFullName($user->employee);
            $avatar = $this->getAvatar($fullName);
            
            // Логируем успешный вход
            $this->logAuth(
                'login',
                'success',
                $user->username,
                $user->id,
                $user->employee_id,
                'Login successful'
            );

            return response()->json([
                'success' => true,
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user' => [
                    'id' => $user->id,
                    'name' => $fullName,
                    'email' => $user->employee?->email,
                    'role' => $user->role,
                    'avatar' => $avatar,
                    'username' => $user->username,
                    'employee_id' => $user->employee_id
                ]
            ], 200);

        } catch (\Exception $e) {
            $this->logAuth(
                'login',
                'failed',
                $request->username ?? null,
                null,
                null,
                'Login error: ' . $e->getMessage()
            );
            
            return response()->json([
                'success' => false,
                'message' => 'Ошибка входа',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /auth/logout - Выход из системы
     */
    public function logout(Request $request)
    {
        try {
            $user = $request->user();
            
            if ($user) {
                // Логируем выход
                $this->logAuth(
                    'logout',
                    'success',
                    $user->username,
                    $user->id,
                    $user->employee_id,
                    'Logout successful'
                );
                
                $user->currentAccessToken()->delete();
            }

            return response()->json([
                'success' => true,
                'message' => 'Выход выполнен успешно'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка выхода',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /auth/me - Получение текущего пользователя
     */
    public function me(Request $request)
    {
        try {
            $user = $request->user();
            $user->load('employee');

            $fullName = $this->getFullName($user->employee);
            $permissions = $this->getPermissionsByRole($user->role);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => $fullName,
                    'email' => $user->employee?->email,
                    'role' => $user->role,
                    'permissions' => $permissions,
                    'employee_id' => $user->employee_id,
                    'username' => $user->username,
                    'status' => $user->status,
                    'last_login' => $user->last_login?->toISOString()
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка получения данных пользователя',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * POST /auth/refresh - Обновление токена
     */
    public function refresh(Request $request)
    {
        try {
            $user = $request->user();
            
            // Удаляем текущий токен
            $user->currentAccessToken()->delete();
            
            // Создаем новый токен
            $token = $user->createToken('auth_token')->plainTextToken;
            
            $this->logAuth(
                'refresh',
                'success',
                $user->username,
                $user->id,
                $user->employee_id,
                'Token refreshed'
            );
            
            return response()->json([
                'success' => true,
                'access_token' => $token,
                'token_type' => 'Bearer'
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh token',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получить права доступа по роли
     */
    private function getPermissionsByRole($role)
    {
        $permissions = [
            'admin' => [
                'view_all', 'edit_all', 'delete_all', 
                'manage_users', 'manage_settings', 'view_reports',
                'manage_trainings', 'manage_courses', 'manage_matrix'
            ],
            'hr_manager' => [
                'view_all', 'edit_employees', 'manage_brigades',
                'view_reports', 'manage_trainings', 'view_matrix'
            ],
            'training_curator' => [
                'view_all', 'manage_trainings', 'manage_courses',
                'view_reports', 'view_matrix', 'assign_trainings'
            ],
            'user' => [
                'view_own', 'view_brigade', 'view_trainings'
            ]
        ];

        return $permissions[$role] ?? $permissions['user'];
    }

    /**
     * Получить полное имя сотрудника
     */
    private function getFullName($employee)
    {
        if (!$employee) {
            return 'Пользователь';
        }

        $fullName = trim(implode(' ', array_filter([
            $employee->last_name,
            $employee->first_name,
            $employee->middle_name
        ])));

        return !empty($fullName) ? $fullName : ($employee->full_name ?? 'Пользователь');
    }

    /**
     * Получить аватар (инициалы)
     */
    private function getAvatar($name)
    {
        $words = explode(' ', $name);
        $initials = '';
        
        foreach ($words as $word) {
            if (!empty($word)) {
                $initials .= mb_substr($word, 0, 1);
            }
            if (strlen($initials) >= 2) {
                break;
            }
        }
        
        return strtoupper($initials);
    }
}