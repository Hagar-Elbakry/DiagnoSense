<?php

namespace App\Http\Controllers\V1;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChargeWalletRequest;
use App\Helpers\ApiResponse;
use Illuminate\Support\Facades\Log;
use App\Services\PaymobService;
use Exception;


class WalletController extends Controller
{
    public function __construct(
        public PaymobService $paymobService,
    ) {}

    public function store(ChargeWalletRequest $request)
    {
        try{
            $currentUser = $request->user();
            $response = $this->paymobService->createIntention($currentUser, $request->validated());
            $checkoutUrl = config('services.paymob.base_url').'unifiedcheckout/?publicKey='.config('services.paymob.public_key').'&clientSecret='.$response['client_secret'];
            return ApiResponse::success(message: 'Wallet charge initiated successfully', data: ['checkout_url' => $checkoutUrl]);
        }catch (Exception $e) {
            Log::error('Error charging wallet: ' . $e->getMessage());
            return ApiResponse::error(message: 'Failed to charge wallet', status: 500);
        }
    }
}