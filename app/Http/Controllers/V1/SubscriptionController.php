<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;
use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Services\SubscriptionService;
use App\Exceptions\BillingValidationException;
use App\Http\Resources\CurrentSubscriptionResource;
use Exception;


class SubscriptionController extends Controller
{
    public function __construct(
        protected SubscriptionService $subscriptionService
    ) {}

    public function store(Request $request, Plan $plan): JsonResponse
    {
        try{
            $doctor = $request->user()->doctor;
            if (!$doctor) {
                return ApiResponse::error('Doctor not found', 404);
            }

            $this->subscriptionService->subscribeDoctorToPlan($doctor, $plan);
            return ApiResponse::success(
                message: 'Successfully subscribed to the plan!',
                status: 201
            );
        }catch(BillingValidationException $e){
            return ApiResponse::error(message: $e->getMessage(), status: $e->getStatusCode());
        }catch(Exception $e){
            Log::error('Subscription Error: '.$e->getMessage(), ['plan_id' => $plan->id]);

            return ApiResponse::error(
                message: 'An error occurred while processing your subscription. Please try again later.',
                status: 500
            );
        }
    }

    public function switchToPayPerUse(Request $request): JsonResponse
    {
        try{
            $doctor = $request->user()->doctor;
            if(!$doctor){
                return ApiResponse::error('Doctor not found', 404);
            }

            $message = $this->subscriptionService->SwitchToPayPerUse($doctor);
            return ApiResponse::success(
                message: $message,
            );
        }catch(Exception $e){
            Log::error('Pay-Per-Use Error: '.$e->getMessage());

            return ApiResponse::error(
                message: 'An error occurred while processing your pay-per-use request. Please try again later.',
                status: 500
            );
        }
    }

    public function show(Request $request)
    {
        try{
            $doctor = $request->user()->doctor->loadMissing(['wallet', 'activeSubscription', 'latestSubscription']);
            if(!$doctor->billing_mode) {
                return ApiResponse::error(
                    message: 'No active subscription or billing mode found.',
                    data: $doctor->wallet ? ['credits' => $doctor->wallet->balance] : null,
                    status: 404
                );
            }

            return ApiResponse::success(
            message: 'Current billing mode retrieved successfully',
            data: new CurrentSubscriptionResource($doctor),
        );
        }catch(Exception $e){
            Log::error('Show Subscription Error: '.$e->getMessage());

            return ApiResponse::error(
                message: 'An error occurred while retrieving your subscription details. Please try again later.',
                status: 500
            );
        }
    }

    public function update(Request $request): JsonResponse
    {
        try{
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
