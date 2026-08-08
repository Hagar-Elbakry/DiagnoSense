<?php

namespace App\Http\Controllers\V1;

use App\Exceptions\BillingValidationException;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\CurrentSubscriptionResource;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use App\Services\SubscriptionService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{
    public function __construct(
        protected SubscriptionService $subscriptionService
    ) {}

    public function index(): JsonResponse
    {
        try {
            $plans = Plan::all();

            return ApiResponse::success(
                message: 'Available plans retrieved successfully',
                data: PlanResource::collection($plans)
            );
        } catch (Exception $e) {
            Log::error('Error retrieving plans: '.$e->getMessage());

            return ApiResponse::error(
                message: 'An error occurred while retrieving plans.',
                status: 500
            );
        }
    }

    public function store(Request $request, Plan $plan): JsonResponse
    {
        try {
            $doctor = $request->user()->doctor;
            if (! $doctor) {
                return ApiResponse::error('Doctor not found', 404);
            }

            $this->subscriptionService->subscribeDoctorToPlan($doctor, $plan);

            return ApiResponse::success(
                message: 'Successfully subscribed to the plan!',
                status: 201
            );
        } catch (BillingValidationException $e) {
            return ApiResponse::error(message: $e->getMessage(), status: $e->getStatusCode());
        } catch (Exception $e) {
            Log::error('Subscription Error: '.$e->getMessage(), ['plan_id' => $plan->id]);

            return ApiResponse::error(
                message: 'An error occurred while processing your subscription. Please try again later.',
                status: 500
            );
        }
    }

    public function switchToPayPerUse(Request $request): JsonResponse
    {
        try {
            $doctor = $request->user()->doctor;
            if (! $doctor) {
                return ApiResponse::error('Doctor not found', 404);
            }

            $message = $this->subscriptionService->SwitchToPayPerUse($doctor);

            return ApiResponse::success(
                message: $message,
            );
        } catch (Exception $e) {
            Log::error('Pay-Per-Use Error: '.$e->getMessage());

            return ApiResponse::error(
                message: 'An error occurred while processing your pay-per-use request. Please try again later.',
                status: 500
            );
        }
    }

    public function show(Request $request)
    {
        try {
            $doctor = $request->user()->doctor->loadMissing(['wallet', 'activeSubscription', 'latestSubscription']);
            $status = $doctor->currentSubscriptionStatus();
            if ($status->mode === 'none') {
                return ApiResponse::error(
                    message: 'No active subscription or billing mode found.',
                    data: $doctor->wallet ? ['credits' => $doctor->wallet->balance] : null,
                    status: 404
                );
            }

            return ApiResponse::success(
                message: 'Current billing mode retrieved successfully',
                data: new CurrentSubscriptionResource($doctor, $status),
            );
        } catch (Exception $e) {
            Log::error('Show Subscription Error: '.$e->getMessage());

            return ApiResponse::error(
                message: 'An error occurred while retrieving your subscription details. Please try again later.',
                status: 500
            );
        }
    }

    public function update(Request $request): JsonResponse
    {
        try {
            $doctor = $request->user()->doctor->loadMissing(['activeSubscription']);

            $message = $this->subscriptionService->cancelSubscription($doctor);

            return ApiResponse::success(message: $message);
        } catch (BillingValidationException $e) {
            return ApiResponse::error(message: $e->getMessage(), status: $e->getStatusCode());
        } catch (Exception $e) {
            Log::error('Subscription Cancellation Error: '.$e->getMessage());

            return ApiResponse::error(message: 'An error occurred while cancelling your subscription.', status: 500);
        }
    }
}
