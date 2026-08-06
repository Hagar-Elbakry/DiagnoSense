<?php

use function Pest\Laravel\actingAs;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->doctor = createUserWithType('doctor', fake()->unique()->safeEmail());
    actingAs($this->doctor);
    Http::fake([
        config('services.paymob.base_url').'v1/intention/' => Http::response([
            'client_secret' => 'test-client-secret',
        ], 200),
    ]);
});

function fakePaymobObj(int $doctorId): array
{
    return [
        'amount_cents' => 10000,
        'created_at' => '2024-01-01T00:00:00',
        'currency' => 'EGP',
        'error_occured' => false,
        'has_parent_transaction' => false,
        'id' => 123456,
        'integration_id' => 12345,
        'is_3d_secure' => false,
        'is_auth' => false,
        'is_capture' => false,
        'is_refunded' => false,
        'is_standalone_payment' => true,
        'is_voided' => false,
        'order' => [
            'id' => 98765,
            'merchant_order_id' => $doctorId.'-'.time(),
        ],
        'owner' => 111,
        'pending' => false,
        'source_data' => [
            'pan' => '2346',
            'sub_type' => 'MasterCard',
            'type' => 'card',
        ],
        'success' => true,
    ];
}

function generatePaymobHmac(array $obj): string
{
    $boolToString = function (bool $value) {
        if (is_string($value)) {
            return $value;
        }

        return $value ? 'true' : 'false';
    };

    $string = $obj['amount_cents'].
        $obj['created_at'].
        $obj['currency'].
        $boolToString($obj['error_occured']).
        $boolToString($obj['has_parent_transaction']).
        $obj['id'].
        $obj['integration_id'].
        $boolToString($obj['is_3d_secure']).
        $boolToString($obj['is_auth']).
        $boolToString($obj['is_capture']).
        $boolToString($obj['is_refunded']).
        $boolToString($obj['is_standalone_payment']).
        $boolToString($obj['is_voided']).
        $obj['order']['id'].
        $obj['owner'].
        $boolToString($obj['pending']).
        ($obj['source_data']['pan'] ?? '').
        ($obj['source_data']['sub_type'] ?? '').
        ($obj['source_data']['type'] ?? '').
        $boolToString($obj['success']);

    return hash_hmac('sha512', $string, config('services.paymob.hmac_secret'));
}

it('generates a payment checkout url for wallet charging', function () {
    $response = $this->postJson(route('wallets.charge'), [
        'balance' => 10000,
    ]);
    $response->assertStatus(200);
    $response->assertJsonStructure([
        'success',
        'message',
        'data' => [
            'checkout_url',
        ],
    ]);
});

it('valid webhook records transaction and updates wallet', function () {
    $obj = fakePaymobObj($this->doctor->doctor->id);
    $hmac = generatePaymobHmac($obj);
    $url = route('paymob.webhook').'?hmac='.$hmac;
    $response = $this->postJson($url, ['obj' => $obj]);
    $response->assertStatus(200);
    $this->assertDatabaseHas('transactions', [
        'doctor_id' => $this->doctor->doctor->id,
        'amount' => 100,
        'status' => 'completed',
    ]);
    $this->assertDatabaseHas('wallets', [
        'doctor_id' => $this->doctor->doctor->id,
        'balance' => 100,
    ]);
});

it('fails webhook if hmac is missing', function () {
    $obj = fakePaymobObj($this->doctor->doctor->id);
    $response = $this->postJson(route('paymob.webhook'), ['obj' => $obj]);
    $response->assertStatus(400);
});

it('fails webhook if hmac is invalid', function () {
    $obj = fakePaymobObj($this->doctor->doctor->id);
    $hmac = generatePaymobHmac($obj);
    $url = route('paymob.webhook').'?hmac='.$hmac.'invalid';
    $response = $this->postJson($url, ['obj' => $obj]);
    $response->assertStatus(401);
});
