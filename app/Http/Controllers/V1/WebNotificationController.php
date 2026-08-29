<?php

namespace App\Http\Controllers\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\MarkNotificationAsReadRequest;
use App\Http\Resources\WebNotificationResource;
use App\Services\WebNotificationService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Log;

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

    public function unreadCount(Request $request): JsonResponse
    {
        try {
            $count = $this->webNotificationService->getUnreadCount($request->user()->doctor);

            return ApiResponse::success(
                message: 'Unread notifications count retrieved successfully.',
                data: ['unread_count' => $count]
            );
        } catch (Exception $e) {
            Log::error('Failed to count notifications: '.$e->getMessage());

            return ApiResponse::error(message: 'Could not retrieve unread count.', status: 500);
        }
    }

    public function read(MarkNotificationAsReadRequest $request, DatabaseNotification $notification): JsonResponse
    {
        try {
            $this->webNotificationService->read($notification);

            return ApiResponse::success(message: 'Notification marked as read');
        } catch (Exception $e) {
            Log::error('Failed to mark notification as read: '.$e->getMessage());

            return ApiResponse::error(message: 'Could not mark notification as read.', status: 500);
        }
    }

    public function readAll(Request $request): JsonResponse
    {
        try {
            $this->webNotificationService->readAll($request->user()->doctor);

            return ApiResponse::success(message: 'All notifications marked as read');
        } catch (Exception $e) {
            Log::error('Failed to mark all notifications as read: '.$e->getMessage());

            return ApiResponse::error(message: 'Could not mark all notifications as read.', status: 500);
        }
    }

    public function clearAll(Request $request): JsonResponse
    {
        try {
            $this->webNotificationService->clearAll($request->user()->doctor);

            return ApiResponse::success(message: 'All notifications deleted successfully');
        } catch (Exception $e) {
            Log::error('Failed to clear all notifications: '.$e->getMessage(), ['exception' => $e]);

            return ApiResponse::error(message: 'Could not clear notifications at the moment.', status: 500);
        }
    }
}
