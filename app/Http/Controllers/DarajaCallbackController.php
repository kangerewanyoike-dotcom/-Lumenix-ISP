<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessPaymentCallback;
use App\Models\DarajaAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DarajaCallbackController extends Controller
{
    public function validateC2b(string $token): JsonResponse
    {
        $exists = DarajaAccount::withoutGlobalScopes()->where('callback_token_hash', hash('sha256', $token))->where('active', true)->exists();
        abort_unless($exists, 404);

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }

    public function stk(Request $request, string $token): JsonResponse
    {
        $account = DarajaAccount::withoutGlobalScopes()->where('callback_token_hash', hash('sha256', $token))->where('active', true)->first();
        abort_unless($account, 404);
        $data = $request->json()->all();
        abort_unless(is_array(data_get($data, 'Body.stkCallback')) && isset($data['Body']['stkCallback']['CheckoutRequestID'], $data['Body']['stkCallback']['ResultCode']), 400);

        ProcessPaymentCallback::dispatch((int) $account->id, 'stk', $data)->afterCommit();

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }

    public function c2b(Request $request, string $token): JsonResponse
    {
        $account = DarajaAccount::withoutGlobalScopes()->where('callback_token_hash', hash('sha256', $token))->where('active', true)->first();
        abort_unless($account, 404);
        $data = $request->all();
        abort_unless(isset($data['TransID'], $data['TransAmount'], $data['ShortCode']) && (string) $data['ShortCode'] === $account->short_code, 400);

        ProcessPaymentCallback::dispatch((int) $account->id, 'c2b', $data)->afterCommit();

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }
}
