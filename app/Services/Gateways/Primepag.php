<?php

namespace App\Services\Gateways;

use App\Models\Gateway;
use App\Models\Withdrawal;
use App\Traits\Gateways\SuitpayTrait;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class Primepag
{
    use SuitpayTrait;

    public Client $client;
    private string $URL = "https://api.primepag.com.br";
    private Gateway $gateway;

    public function __construct()
    {
        $this->client = new Client(['verify' => false]);
        $gateway = Gateway::first();
        if (!$gateway) {
            throw new Exception('Configurações de gateways não encontrada');
        }
        $this->gateway = $gateway;
    }

    public function getAccessToken()
    {

        try {

            $key = base64_encode($this->gateway->primepag_client_id . ':' . $this->gateway->primepag_client_secret);

            $response = $this->client->request(
                "POST",
                "{$this->URL}/auth/generate_token",
                [
                    "headers" => [
                        "Content-Type" => "application/json",
                        "User-Agent" => config('app.name') . "(barraroot@gmail.com)",
                        "Authorization" => "BASIC " . $key,
                    ],
                    "body" => json_encode([
                        "grant_type" => "client_credentials"
                    ])
                ]
            );

            $dataResponse = json_decode($response->getBody(), true);

            Log::debug('Token: ' . $dataResponse['access_token']);

            return $dataResponse['access_token'];

        } catch (\Throwable $exception) {
            Log::debug($exception->getMessage());
            return null;
        }
    }

    public function registerWebHook()
    {
        $token = $this->getAccessToken();

        $response = $this->client->request(
            "GET",
            "{$this->URL}/v1/webhooks/types",
            [
                "headers" => [
                    "Content-Type" => "application/json",
                    "User-Agent" => config('app.name') . "(barraroot@gmail.com)",
                    "Authorization" => "Bearer {$token}",
                ],
            ]
        );

        $dataResponse = json_decode($response->getBody(), true);

        foreach ($dataResponse['webhook_types'] as $type) {
            $response = $this->client->request(
                "POST",
                "{$this->URL}/v1/webhooks/{$type['id']}",
                [
                    "headers" => [
                        "Content-Type" => "application/json",
                        "User-Agent" => config('app.name') . "(barraroot@gmail.com)",
                        "Authorization" => "Bearer {$token}",
                    ],
                    "body" => json_encode([
                        "url" => config('app.url') . '/api/webhook/primepag',
                        "authorization" => $this->gateway->primepag_webhook
                    ])
                ]
            );
            /*
            $dataResponse = json_decode($response->getBody(), true);
            dd($dataResponse);
            */
        }
    }

    public function getPay($cpf, $amount, $acceptedBonus)
    {
        try {
            $accessToken = $this->getAccessToken();
            $response = $this->client->request(
                "POST",
                "{$this->URL}/v1/pix/qrcodes",
                [
                    "headers" => [
                        "Authorization" => "Bearer {$accessToken}",
                        "Content-Type" => "application/json",
                        "User-Agent" => "Axtro bet(edito.desenvolvedor@gmail.com)"
                    ],
                    "body" => json_encode([
                        "value_cents" => \Helper::amountPrepare($amount, true),
                        "generator_name" => auth('api')->user()->name,
                        "generator_document" => $cpf,
                    ])
                ]
            );

            $dataResponse = json_decode($response->getBody(), true);

            $array['reference_code'] = $dataResponse['qrcode']['reference_code'];
            $array['content'] = $dataResponse['qrcode']["image_base64"];//["pix_key"];
            $array['image_url'] = $dataResponse['qrcode']["content"];//["qr_code_url"];

            self::generateTransaction($dataResponse['qrcode']['reference_code'], \Helper::amountPrepare($amount), $acceptedBonus); /// gerando historico
            self::generateDeposit($dataResponse['qrcode']['reference_code'], \Helper::amountPrepare($amount), $acceptedBonus); /// gerando deposito

            return [
                'status' => true,
                'idTransaction' => $dataResponse['qrcode']['reference_code'],
                'qrcode' => $dataResponse['qrcode']["content"]
            ];

        } catch (\Throwable $exception) {
            return response()->json(["message" => $exception->getMessage()], 401);
        }
    }

    public function confirmPayment($idTransaction)
    {
        self::finalizePayment($idTransaction);
    }

    public function pixCashout(Withdrawal $transaction)
    {
        try {
            $accessToken = $this->getAccessToken();
            $pix_type = match ($transaction->pix_type) {
                'document' => $this->getType($transaction->pix_key),
                'phoneNumber' => 'phone',
                'randomKey' => "token",
                default => $transaction->pix_type
            };

            $key = match ($transaction->pix_type) {
                'document' => \Helper::soNumero($transaction->pix_key),
                'phoneNumber' => "+55" . \Helper::soNumero($transaction->pix_key),
                default => $transaction->pix_key
            };

            $response = $this->client->request(
                "POST",
                "{$this->URL}/v1/pix/payments",
                [
                    "headers" => [
                        "Authorization" => "Bearer {$accessToken}",
                        "Content-Type" => "application/json",
                        "User-Agent" => "Axtro bet(edito.desenvolvedor@gmail.com)"
                    ],
                    "body" => json_encode([
                        "value_cents" => \Helper::amountPrepare($transaction->amount, true),
                        "initiation_type" => "dict",
                        "idempotent_id" => $transaction->id . uniqid(),
                        "pix_key_type" => $pix_type,
                        "pix_key" => $key,
                        "authorized" => true
                    ])
                ]
            );

            $resp = json_decode($response->getBody());

            if ($resp?->payment !== null) {
                return true;
            }

            return false;
        } catch (\Throwable $exception) {
            return false;
        }
    }

    public function getType($keyType){
        if(strlen(\Helper::soNumero($keyType)) === 11){
            return "cpf";
        }

        return "cnpj";
    }

}
