<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TrainingEvent;
use App\Models\TrainingEventParticipant;
use App\Models\Course;
use App\Models\Employee;
use App\Models\EmployeeCourse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TrainingEventController extends Controller
{
    /**
     * Принудительная очистка ВСЕГО кэша, связанного с обучениями
     */
    private function clearAllTrainingCache()
    {
        // 1. Очищаем маппинг мероприятий
        Cache::forget('event_mapping');
        
        // 2. Очищаем общую статистику
        Cache::forget('trainings_statistics');
        
        // 3. Полностью очищаем ВСЕ ключи trainings_*
        // Это самый надежный способ
        $this->clearKeysByPrefix('trainings_');
        
        // 4. Дополнительная очистка специфических ключей
        Cache::forget('trainings_summary_*');
        Cache::forget('trainings_expired_*');
        Cache::forget('trainings_expiring_*');
        
        // 5. Очищаем кэш бригад (может содержать связанные данные)
        Cache::forget('brigade_trainings_*');
        
        // 6. Очищаем кэш сотрудников
        Cache::forget('employee_trainings_*');
        
        // 7. Очищаем кэш тепловой карты
        Cache::forget('heatmap_*');
    }
    
    /**
     * Очистка всех ключей по префиксу
     */
    private function clearKeysByPrefix($prefix)
    {
        try {
            // Для Redis драйвера
            if (Cache::getStore() instanceof \Illuminate\Cache\RedisStore) {
                $redis = Cache::getStore()->getRedis();
                $keys = $redis->keys("*{$prefix}*");
                foreach ($keys as $key) {
                    $redis->del($key);
                }
            } else {
                // Для file и database драйверов - очищаем весь кэш
                Cache::flush();
            }
        } catch (\Exception $e) {
            // Если не получилось, очищаем всё
            Cache::flush();
        }
    }
    
    /**
     * POST /training-events/{id}/participants - Добавить участников
     */
    public function addParticipants(Request $request, $id)
    {
        try {
            $event = TrainingEvent::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'employee_ids' => 'required|array|min:1',
                'employee_ids.*' => 'exists:employees,id',
                'status' => 'in:registered,confirmed'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            DB::beginTransaction();
            
            $added = 0;
            $skipped = 0;
            
            foreach ($request->employee_ids as $employeeId) {
                $exists = TrainingEventParticipant::where('event_id', $id)
                    ->where('employee_id', $employeeId)
                    ->exists();
                
                if (!$exists) {
                    TrainingEventParticipant::create([
                        'event_id' => $id,
                        'employee_id' => $employeeId,
                        'status' => $request->get('status', 'registered')
                    ]);
                    $added++;
                } else {
                    $skipped++;
                }
            }
            
            DB::commit();
            
            // КРИТИЧНО: Очищаем ВЕСЬ кэш после изменения
            $this->clearAllTrainingCache();
            
            return response()->json([
                'success' => true,
                'message' => "Добавлено {$added} участников. Пропущено: {$skipped}",
                'added' => $added,
                'skipped' => $skipped
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json(['error' => 'Event not found'], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Add participants error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to add participants', 'message' => $e->getMessage()], 500);
        }
    }
    
    /**
     * POST /training-events/{id}/participants/remove-bulk - Массовое удаление участников
     */
    public function removeParticipantsBulk(Request $request, $id)
    {
        try {
            $event = TrainingEvent::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'participant_ids' => 'required|array|min:1',
                'participant_ids.*' => 'exists:training_event_participants,id'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $deleted = TrainingEventParticipant::where('event_id', $id)
                ->whereIn('id', $request->participant_ids)
                ->delete();
            
            // КРИТИЧНО: Очищаем ВЕСЬ кэш после изменения
            $this->clearAllTrainingCache();
            
            return response()->json([
                'success' => true,
                'message' => "Удалено {$deleted} участников",
                'deleted_count' => $deleted
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Event not found'], 404);
        } catch (\Exception $e) {
            \Log::error('Remove participants bulk error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to remove participants', 'message' => $e->getMessage()], 500);
        }
    }
    
    /**
     * DELETE /training-events/{id}/participants/{participantId} - Удалить участника
     */
    public function removeParticipant($id, $participantId)
    {
        try {
            $participant = TrainingEventParticipant::where('event_id', $id)
                ->where('id', $participantId)
                ->firstOrFail();
            
            $participant->delete();
            
            // КРИТИЧНО: Очищаем ВЕСЬ кэш после изменения
            $this->clearAllTrainingCache();
            
            return response()->json([
                'success' => true,
                'message' => 'Участник удален'
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Participant not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to remove participant', 'message' => $e->getMessage()], 500);
        }
    }
    
    /**
     * DELETE /training-events/{id}/participants - Удалить всех участников
     */
    public function removeAllParticipants($id)
    {
        try {
            $event = TrainingEvent::findOrFail($id);
            
            $deleted = TrainingEventParticipant::where('event_id', $id)->delete();
            
            // КРИТИЧНО: Очищаем ВЕСЬ кэш после изменения
            $this->clearAllTrainingCache();
            
            return response()->json([
                'success' => true,
                'message' => "Удалены все участники ({$deleted})",
                'deleted_count' => $deleted
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Event not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to remove participants', 'message' => $e->getMessage()], 500);
        }
    }
    
    /**
     * PUT /training-events/{id}/participants/{participantId}/status - Обновить статус участника
     */
    public function updateParticipantStatus(Request $request, $id, $participantId)
    {
        try {
            $event = TrainingEvent::findOrFail($id);
            $participant = TrainingEventParticipant::where('event_id', $id)
                ->where('id', $participantId)
                ->firstOrFail();
            
            $validator = Validator::make($request->all(), [
                'status' => 'required|in:registered,confirmed,attended,absent,cancelled',
                'completion_date' => 'nullable|date',
                'certificate_number' => 'nullable|string|max:100'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $participant->update($request->only(['status', 'completion_date', 'certificate_number']));
            
            // Если участник посетил мероприятие, обновляем его обучение
            if ($request->status === 'attended' && $request->completion_date) {
                $this->updateEmployeeTraining($participant->employee_id, $event->course_id, $request->completion_date);
            }
            
            // КРИТИЧНО: Очищаем ВЕСЬ кэш после изменения
            $this->clearAllTrainingCache();
            
            return response()->json([
                'success' => true,
                'message' => 'Статус участника обновлен',
                'participant' => $participant
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Participant not found'], 404);
        } catch (\Exception $e) {
            \Log::error('Update participant status error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to update participant status', 'message' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Обновить обучение сотрудника
     */
    private function updateEmployeeTraining($employeeId, $courseId, $completionDate)
    {
        $training = EmployeeCourse::where('employee_id', $employeeId)
            ->where('course_id', $courseId)
            ->first();
        
        if ($training) {
            $course = Course::find($courseId);
            $periodicityMonths = $course?->periodicity_months ?? 12;
            $newExpirationDate = Carbon::parse($completionDate)->addMonths($periodicityMonths);
            
            $training->update([
                'status' => 'active',
                'completed_date' => $completionDate,
                'expiration_date' => $newExpirationDate
            ]);
        }
    }
    
    /**
     * GET /training-events - Список мероприятий (для календаря)
     */
    public function index(Request $request)
    {
        try {
            $query = TrainingEvent::with(['course', 'participants.employee']);
            
            // Фильтр по курсу
            if ($request->has('course_id')) {
                $query->where('course_id', $request->course_id);
            }
            
            // Фильтр по статусу
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
            
            // Фильтр по формату
            if ($request->has('format')) {
                $query->where('format', $request->format);
            }
            
            // Фильтр по датам
            if ($request->has('start_date')) {
                $query->where('start_date', '>=', $request->start_date);
            }
            if ($request->has('end_date')) {
                $query->where('end_date', '<=', $request->end_date);
            }
            
            // Поиск
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('title', 'LIKE', "%{$search}%")
                      ->orWhere('location', 'LIKE', "%{$search}%")
                      ->orWhere('training_center', 'LIKE', "%{$search}%");
                });
            }
            
            // Сортировка
            $sortField = $request->get('sort_by', 'start_date');
            $sortDirection = $request->get('sort_direction', 'asc');
            $query->orderBy($sortField, $sortDirection);
            
            $events = $query->get();
            
            $formattedEvents = $events->map(function($event) {
                return [
                    'id' => $event->id,
                    'title' => $event->title,
                    'course' => [
                        'id' => $event->course->id,
                        'name' => $event->course->name
                    ],
                    'format' => $event->format,
                    'format_label' => $this->getFormatLabel($event->format),
                    'start_date' => $event->start_date->format('Y-m-d'),
                    'end_date' => $event->end_date?->format('Y-m-d'),
                    'location' => $event->location,
                    'training_center' => $event->training_center,
                    'status' => $event->status,
                    'status_label' => $this->getStatusLabel($event->status),
                    'status_color' => $this->getStatusColor($event->status),
                    'cost' => $event->cost,
                    'participants_count' => $event->participants->count(),
                    'max_participants' => $event->max_participants,
                    'available_slots' => $event->available_slots
                ];
            });
            
            return response()->json([
                'events' => $formattedEvents,
                'total' => $events->count()
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch events',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * GET /training-events/calendar - Данные для календаря (FullCalendar)
     */
    public function calendar(Request $request)
    {
        try {
            $startDate = $request->get('start');
            $endDate = $request->get('end');
            
            $query = TrainingEvent::with('course');
            
            if ($startDate && $endDate) {
                $query->where(function($q) use ($startDate, $endDate) {
                    $q->whereBetween('start_date', [$startDate, $endDate])
                      ->orWhereBetween('end_date', [$startDate, $endDate]);
                });
            }
            
            $events = $query->get();
            
            $calendarEvents = $events->map(function($event) {
                return [
                    'id' => $event->id,
                    'title' => $event->title,
                    'start' => $event->start_date->format('Y-m-d'),
                    'end' => $event->end_date?->format('Y-m-d'),
                    'url' => "/training-events/{$event->id}",
                    'backgroundColor' => $this->getStatusColor($event->status),
                    'borderColor' => $this->getStatusColor($event->status),
                    'extendedProps' => [
                        'course' => $event->course->name,
                        'format' => $this->getFormatLabel($event->format),
                        'location' => $event->location,
                        'status' => $event->status,
                        'participants' => $event->participants->count()
                    ]
                ];
            });
            
            return response()->json($calendarEvents, 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch calendar data',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * GET /training-events/{id} - Детали мероприятия
     */
    public function show($id)
    {
        try {
            $event = TrainingEvent::with(['course', 'participants.employee.position', 'participants.employee.department'])
                ->findOrFail($id);
            
            $participants = $event->participants->filter(function($participant) {
                return $participant->employee !== null;
            })->map(function($participant) {
                $employee = $participant->employee;
                $fullName = trim(implode(' ', array_filter([
                    $employee->last_name,
                    $employee->first_name,
                    $employee->middle_name
                ])));
                
                return [
                    'id' => $participant->id,
                    'employee_id' => $employee->id,
                    'employee_name' => $fullName ?: $employee->full_name,
                    'personnel_number' => $employee->personnel_number,
                    'position' => $employee->position?->name,
                    'department' => $employee->department?->name,
                    'status' => $participant->status,
                    'status_label' => $this->getParticipantStatusLabel($participant->status),
                    'completion_date' => $participant->completion_date?->format('Y-m-d'),
                    'certificate_number' => $participant->certificate_number,
                    'notes' => $participant->notes
                ];
            });
            
            return response()->json([
                'id' => $event->id,
                'title' => $event->title,
                'course' => [
                    'id' => $event->course->id,
                    'name' => $event->course->name,
                    'duration_hours' => $event->course->duration_hours,
                    'periodicity_months' => $event->course->periodicity_months
                ],
                'format' => $event->format,
                'format_label' => $this->getFormatLabel($event->format),
                'start_date' => $event->start_date->format('Y-m-d'),
                'end_date' => $event->end_date?->format('Y-m-d'),
                'location' => $event->location,
                'training_center' => $event->training_center,
                'status' => $event->status,
                'status_label' => $this->getStatusLabel($event->status),
                'cost' => $event->cost,
                'max_participants' => $event->max_participants,
                'notes' => $event->notes,
                'participants' => $participants,
                'participants_count' => $participants->count(),
                'created_at' => $event->created_at->toISOString(),
                'updated_at' => $event->updated_at->toISOString()
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Event not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch event details',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * POST   /training-events - Создание мероприятия
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:200',
                'course_id' => 'required|exists:courses,id',
                'format' => 'required|in:onsite,online,hybrid',
                'start_date' => 'required|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'location' => 'nullable|string|max:255',
                'training_center' => 'nullable|string|max:200',
                'status' => 'in:draft,planned,confirmed,in_progress,completed,cancelled,awaiting_confirmation',
                'cost' => 'nullable|numeric|min:0',
                'max_participants' => 'nullable|integer|min:1',
                'notes' => 'nullable|string',
                'participant_ids' => 'nullable|array',
                'participant_ids.*' => 'exists:employees,id'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            DB::beginTransaction();
            
            $event = TrainingEvent::create($request->except('participant_ids'));
            
            // Добавляем участников
            if ($request->has('participant_ids')) {
                foreach ($request->participant_ids as $employeeId) {
                    TrainingEventParticipant::create([
                        'event_id' => $event->id,
                        'employee_id' => $employeeId,
                        'status' => 'registered'
                    ]);
                }
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Мероприятие создано',
                'event' => $event
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to create event',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * PUT /training-events/{id} - Обновление мероприятия
     */
    public function update(Request $request, $id)
    {
        try {
            $event = TrainingEvent::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|string|max:200',
                'course_id' => 'sometimes|exists:courses,id',
                'format' => 'sometimes|in:onsite,online,hybrid',
                'start_date' => 'sometimes|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'location' => 'nullable|string|max:255',
                'training_center' => 'nullable|string|max:200',
                'status' => 'in:draft,planned,confirmed,in_progress,completed,cancelled,awaiting_confirmation',
                'cost' => 'nullable|numeric|min:0',
                'max_participants' => 'nullable|integer|min:1',
                'notes' => 'nullable|string'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $event->update($request->all());
            
            return response()->json([
                'success' => true,
                'message' => 'Мероприятие обновлено',
                'event' => $event
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Event not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update event',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * DELETE /training-events/{id} - Удаление мероприятия
     */
    public function destroy($id)
    {
        try {
            $event = TrainingEvent::findOrFail($id);
            $event->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Мероприятие удалено'
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Event not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to delete event',
                'message' => $e->getMessage()
            ], 500);
        }
    }
   
    
    private function getFormatLabel($format)
    {
        return [
            'onsite' => 'Очное',
            'online' => 'Онлайн',
            'hybrid' => 'Гибридное'
        ][$format] ?? $format;
    }
    
    private function getStatusLabel($status)
    {
        return [
            'draft' => 'Черновик',
            'confirmed' => 'Подтверждено',
            'in_progress' => 'В процессе',
            'completed' => 'Завершено',
            'cancelled' => 'Отменено'
        ][$status] ?? $status;
    }
    
    private function getStatusColor($status)
    {
        return [
            'draft' => '#9ca3af',
            'confirmed' => '#10b981',
            'in_progress' => '#3b82f6',
            'completed' => '#8b5cf6',
            'cancelled' => '#ef4444'
        ][$status] ?? '#9ca3af';
    }
    
    private function getParticipantStatusLabel($status)
    {
        return [
            'registered' => 'Зарегистрирован',
            'confirmed' => 'Подтвержден',
            'attended' => 'Посетил',
            'absent' => 'Отсутствовал',
            'cancelled' => 'Отменен'
        ][$status] ?? $status;
    }

    
}