<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSupportRequest;
use App\Actions\StoreSupportTicketAction;
use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Exception;

class SupportController extends Controller
{
    public function __invoke(
        StoreSupportRequest $request,
        StoreSupportTicketAction $supportTicketAction
    ): JsonResponse {
        try {
            $supportTicketAction->execute(
                $request->validated(),
                $request->user()
            );

            return ApiResponse::success(
                message: 'Support message submitted successfully we will get back to you shortly.',
                status: 201
            );

        } catch (Exception $e) {
            Log::error('Error submitting support message: '.$e->getMessage(), [
                'exception' => $e,
                'user_id' => $request->user()?->id,
            ]);

            return ApiResponse::error(
                message: 'Failed to submit message.',
                status: 500
            );
        }
    }
}