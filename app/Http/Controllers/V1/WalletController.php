<?php

namespace App\Http\Controllers\V1;

use App\Actions\GetTransactionHistoryAction;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChargeWalletRequest;
use App\Services\PaymobService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WalletController extends Controller
{
    public function __construct(
        public PaymobService $paymobService,
    ) {}

    public function index(Request $request, GetTransactionHistoryAction $action): JsonResponse
    {
        try {
            $currentDoctor = $request->user()->doctor;
            $data = $action->execute($currentDoctor);

            return ApiResponse::success(message: 'Wallet transactions retrieved successfully', data: $data);
        } catch (Exception $e) {
            Log::error('Error retrieving wallet transactions: '.$e->getMessage());

            return ApiResponse::error(message: 'Failed to retrieve wallet transactions', status: 500);
        }
    }

    public function store(ChargeWalletRequest $request): JsonResponse
    {
        try {
            $currentUser = $request->user();
            $response = $this->paymobService->createIntention($currentUser, $request->validated());
            $checkoutUrl = config('services.paymob.base_url').'unifiedcheckout/?publicKey='.config('services.paymob.public_key').'&clientSecret='.$response['client_secret'];

            return ApiResponse::success(message: 'Wallet charge initiated successfully', data: ['checkout_url' => $checkoutUrl]);
        } catch (Exception $e) {
            Log::error('Error charging wallet: '.$e->getMessage());

            return ApiResponse::error(message: 'Failed to charge wallet', status: 500);
        }
    }
}
