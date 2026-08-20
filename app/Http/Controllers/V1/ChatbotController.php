<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AskChatbotRequest;
use App\Models\Patient;
use App\Services\ChatbotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Helpers\ApiResponse;
use Exception;

class ChatbotController extends Controller
{
    public function __construct(
        protected ChatbotService $chatbotService
    ){}

    public function __invoke(AskChatbotRequest $request, Patient $patient): JsonResponse
    {
        try{
            $question = $request->question;
            if(!$request->user()->doctor->hasFeature('DiagnoBot')){
                return ApiResponse::error(message: 'You need to upgrade to Premium to use the chatbot', status: 403);
            }

            $result = $this->chatbotService->ask($question, $patient, $request->user()->doctor);
            return ApiResponse::success(message: 'Answer from chatbot', data: $result['message'], status: $result['status']);
        } catch(Exception $e) {
            Log::error('Error getting answer from chatbot: '.$e->getMessage(), ['exception' => $e]);

            return ApiResponse::error(message: 'Failed to get answer from chatbot', status: 500);
        }
    }
}