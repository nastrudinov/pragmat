<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserAccount;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class UserController extends Controller
{
    /**
     * GET /users - Список пользователей
     */
    public function index(Request $request)
    {
        try {
            $page = $request->get('page', 1);
            $limit = $request->get('limit', 10);
            
            $query = UserAccount::with('employee');
            
            if ($request->has('role')) {
                $query->where('role', $request->role);
            }
            
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
            
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('username', 'LIKE', "%{$search}%")
                      ->orWhereHas('employee', function($sub) use ($search) {
                          $sub->where('full_name', 'LIKE', "%{$search}%")
                              ->orWhere('email', 'LIKE', "%{$search}%");
                      });
                });
            }
            
            $total = $query->count();
            $users = $query->orderBy('created_at', 'desc')
                          ->skip(($page - 1) * $limit)
                          ->take($limit)
                          ->get();
            
            $formattedUsers = $users->map(function($user) {
                $fullName = $this->getFullName($user->employee);
                
                return [
                    'id' => $user->id,
                    'username' => $user->username,
                    'fullName' => $fullName,
                    'email' => $user->employee?->email,
                    'role' => $user->role,
                    'status' => $user->status,
                    'lastLogin' => $user->last_login?->format('Y-m-d H:i:s'),
                    'createdAt' => $user->created_at?->format('Y-m-d H:i:s')
                ];
            });
            
            return response()->json([
                'users' => $formattedUsers,
                'total' => $total,
                'page' => $page,
                'limit' => $limit
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch users',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * POST /users - Создание пользователя
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'employee_id' => 'required|exists:employees,id|unique:user_accounts,employee_id',
                'username' => 'required|string|max:50|unique:user_accounts,username',
                'role' => 'required|in:admin,hr_manager,training_curator,user',
                'status' => 'in:active,inactive',
                'password' => 'nullable|string|min:6'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $employee = Employee::find($request->employee_id);
            $temporaryPassword = $request->password ?? Str::random(10);
            
            $user = UserAccount::create([
                'employee_id' => $request->employee_id,
                'username' => $request->username,
                'password_hash' => Hash::make($temporaryPassword),
                'role' => $request->role,
                'status' => $request->get('status', 'active')
            ]);
            
            $user->load('employee');
            $fullName = $this->getFullName($employee);
            
            return response()->json([
                'success' => true,
                'message' => 'Пользователь создан',
                'data' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'fullName' => $fullName,
                    'email' => $employee->email,
                    'role' => $user->role,
                    'status' => $user->status,
                    'temporaryPassword' => $request->password ? null : $temporaryPassword,
                    'createdAt' => $user->created_at->toISOString()
                ]
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create user',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * PUT /users/{id} - Обновление пользователя
     */
    public function update(Request $request, $id)
    {
        try {
            $user = UserAccount::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'username' => 'sometimes|string|max:50|unique:user_accounts,username,' . $id,
                'role' => 'sometimes|in:admin,hr_manager,training_curator,user',
                'status' => 'sometimes|in:active,inactive'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            if ($request->has('username')) {
                $user->username = $request->username;
            }
            if ($request->has('role')) {
                $user->role = $request->role;
            }
            if ($request->has('status')) {
                $user->status = $request->status;
            }
            
            $user->save();
            $user->load('employee');
            
            $fullName = $this->getFullName($user->employee);
            
            return response()->json([
                'success' => true,
                'message' => 'Пользователь обновлен',
                'data' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'fullName' => $fullName,
                    'email' => $user->employee?->email,
                    'role' => $user->role,
                    'status' => $user->status,
                    'updatedAt' => $user->updated_at->toISOString()
                ]
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => 'User not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update user',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * DELETE /users/{id} - Удаление пользователя
     */
    public function destroy($id)
    {
        try {
            $user = UserAccount::findOrFail($id);
            
            $adminCount = UserAccount::where('role', 'admin')->count();
            if ($user->role === 'admin' && $adminCount <= 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Нельзя удалить единственного администратора'
                ], 400);
            }
            
            $user->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Пользователь удален'
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Пользователь не найден'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при удалении пользователя',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * PUT /users/{id}/password - Сброс пароля
     */
    public function resetPassword(Request $request, $id)
    {
        try {
            $user = UserAccount::findOrFail($id);
            
            $temporaryPassword = Str::random(10);
            $user->password_hash = Hash::make($temporaryPassword);
            $user->save();
            
            return response()->json([
                'success' => true,
                'message' => 'Пароль успешно сброшен',
                'temporaryPassword' => $temporaryPassword
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Пользователь не найден'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при сбросе пароля',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
   /**
     * PUT /users/password - Смена пароля текущим пользователем
     */
    public function changePassword(Request $request)
    {
        try {
            // Получаем текущего пользователя из токена
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }
            
            $validator = Validator::make($request->all(), [
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:6|confirmed'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            if (!Hash::check($request->current_password, $user->password_hash)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Текущий пароль неверен'
                ], 401);
            }
            
            $user->password_hash = Hash::make($request->new_password);
            $user->save();
            
            // Логируем смену пароля
            \Log::info('Password changed for user: ' . $user->username);
            
            return response()->json([
                'success' => true,
                'message' => 'Пароль успешно изменен'
            ], 200);
            
        } catch (\Exception $e) {
            \Log::error('Change password error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при смене пароля',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * GET /users/roles - Список ролей
     */
    public function getRoles()
    {
        return response()->json([
            'roles' => [
                ['id' => 'admin', 'name' => 'Администратор'],
                ['id' => 'hr_manager', 'name' => 'HR-менеджер'],
                ['id' => 'training_curator', 'name' => 'Куратор обучения'],
                ['id' => 'user', 'name' => 'Пользователь']
            ]
        ], 200);
    }
    
    /**
     * GET /users/employees-without-account - Сотрудники без учетной записи
     */
    public function getEmployeesWithoutAccount()
    {
        try {
            $employees = Employee::whereDoesntHave('userAccount')
                ->where('status', 'active')
                ->get()
                ->map(function($employee) {
                    $fullName = $this->getFullName($employee);
                    return [
                        'id' => $employee->id,
                        'name' => $fullName,
                        'personnel_number' => $employee->personnel_number,
                        'position' => $employee->position?->name,
                        'email' => $employee->email
                    ];
                });
            
            return response()->json([
                'employees' => $employees,
                'total' => $employees->count()
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch employees without account',
                'message' => $e->getMessage()
            ], 500);
        }
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
}