<?php

namespace App\Http\Controllers\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\WebNotificationResource;
use App\Services\WebNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class WebNotificationController extends Controller
{
    public function __construct(
        protected WebNotificationService $webNotificationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $notifications = $this->webNotificationService->getPaginatedUserNotifications($request->user()->doctor);

            return ApiResponse::success(
                message: 'Notifications retrieved successfully.',
                data: WebNotificationResource::collection($notifications)->response()->getData(true)
            );
        } catch (Exception $e) {
            Log::error('Failed to fetch notifications: '.$e->getMessage());

            return ApiResponse::error(message: 'Could not load notifications at the moment.', status: 500);
        }
    }
}