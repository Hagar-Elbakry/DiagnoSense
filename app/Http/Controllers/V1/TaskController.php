<?php

namespace App\Http\Controllers\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteTaskRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Resources\DoctorTaskResource;
use App\Http\Resources\PatientTaskResource;
use App\Models\Task;
use App\Models\Visit;
use App\Services\TaskService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TaskController extends Controller
{
    public function __construct(
        protected TaskService $taskService
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $patient = $request->user()->patient;
            $tasks = $this->taskService->getTasks($patient);

            return ApiResponse::success(
                message: 'Tasks retrieved successfully',
                data: PatientTaskResource::collection($tasks),
            );
        } catch (Exception $e) {
            Log::error('Error fetching tasks: '.$e->getMessage(), ['exception' => $e]);

            return ApiResponse::error(message: 'Failed to retrieve tasks, please try again later.', status: 500);
        }
    }

    public function store(StoreTaskRequest $request, Visit $visit): JsonResponse
    {
        try {
            $data = $request->validated();
            $task = $this->taskService->store($visit, $data);
            if (! $task) {
                return ApiResponse::error(message: 'Next visit date is required for tasks.', status: 422);
            }

            return ApiResponse::success(message: 'Task created successfully', data: new DoctorTaskResource($task));
        } catch (Exception $e) {
            Log::error('Error creating task: '.$e->getMessage(), ['exception' => $e]);

            return ApiResponse::error(message: 'Failed to create task, please try again later.', status: 500);
        }
    }

    public function destroy(DeleteTaskRequest $request, Task $task): JsonResponse
    {
        try {
            $this->taskService->delete($task);

            return ApiResponse::success(message: 'Task deleted successfully');
        } catch (Exception $e) {
            Log::error('Error deleting task: '.$e->getMessage(), ['exception' => $e]);

            return ApiResponse::error(message: 'Failed to delete task, please try again later.', status: 500);
        }
    }
}
