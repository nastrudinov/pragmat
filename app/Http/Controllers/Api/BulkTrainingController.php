<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeeCourse;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BulkTrainingController extends Controller
{
    /**
     * POST /trainings/bulk-update-expiration - Массовое обновление даты истечения
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkUpdateExpiration(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'employee_ids' => 'required|array|min:1',
                'employee_ids.*' => 'exists:employees,id',
                'course_id' => 'required|exists:courses,id',
                'completed_date' => 'required|date',
                'status' => 'nullable|in:active,expiring,expired,required'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $course = Course::find($request->course_id);
            
            if (!$course) {
                return response()->json([
                    'success' => false,
                    'message' => 'Course not found'
                ], 404);
            }

            if (!$course->periodicity_months) {
                return response()->json([
                    'success' => false,
                    'message' => 'Course has no periodicity_months'
                ], 400);
            }

            $completedDate = Carbon::parse($request->completed_date);
            $newExpirationDate = $completedDate->copy()->addMonths($course->periodicity_months);

            $updated = 0;
            $created = 0;
            $skipped = 0;
            $errors = [];

            DB::beginTransaction();

            foreach ($request->employee_ids as $employeeId) {
                try {
                    $training = EmployeeCourse::where('employee_id', $employeeId)
                        ->where('course_id', $request->course_id)
                        ->first();

                    if ($training) {
                        $training->completed_date = $completedDate;
                        $training->expiration_date = $newExpirationDate;
                        $training->status = $request->get('status', 'active');
                        $training->save();
                        $updated++;
                    } else {
                        EmployeeCourse::create([
                            'employee_id' => $employeeId,
                            'course_id' => $request->course_id,
                            'completed_date' => $completedDate,
                            'expiration_date' => $newExpirationDate,
                            'status' => $request->get('status', 'active'),
                            'assigned_date' => now()
                        ]);
                        $created++;
                    }
                } catch (\Exception $e) {
                    $errors[] = [
                        'employee_id' => $employeeId,
                        'error' => $e->getMessage()
                    ];
                    $skipped++;
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Даты истечения успешно обновлены',
                'data' => [
                    'course_id' => $course->id,
                    'course_name' => $course->name,
                    'periodicity_months' => $course->periodicity_months,
                    'completed_date' => $completedDate->format('Y-m-d'),
                    'new_expiration_date' => $newExpirationDate->format('Y-m-d'),
                    'updated' => $updated,
                    'created' => $created,
                    'skipped' => $skipped,
                    'errors' => $errors
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Bulk update expiration error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update expiration dates',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}