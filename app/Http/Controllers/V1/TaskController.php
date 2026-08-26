<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Services\TaskService;
use App\Http\Requests\StoreTaskRequest;
use App\Models\Visit;
use Illuminate\Http\JsonResponse;
use App\Helpers\ApiResponse;
use Illuminate\Support\Facades\Log;
use App\Http\Resources\DoctorTaskResource;
use Exception;

class TaskController extends Controller
{
    public function __construct(
        protected TaskService $taskService
    ) {}

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
}