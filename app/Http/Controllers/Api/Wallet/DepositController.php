<?php

namespace App\Http\Controllers\Api\Wallet;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Services\Gateways\Primepag;
use App\Traits\Gateways\SuitpayTrait;
use Exception;
use Illuminate\Http\Request;

class DepositController extends Controller
{
    use SuitpayTrait;

    /**
     * @param Request $request
     * @return array|false[]
     */
    public function submitPayment(Request $request)
    {
        switch ($request->gateway) {
            case 'suitpay':
                return self::requestQrcode($request);
                break;
            case 'primepag':
                return $this->getQrCodePrimePag($request->all());
        }
    }

    public function getQrCodePrimePag($dados)
    {
        try {
            $primepag = new Primepag();
            return $primepag->getPay($dados['cpf'], $dados['amount'], $dados['accept_bonus']);

        }catch(\Exception $ex) {
            return response()->json(['error' => $ex->getMessage()], 404);
        }

    }

    /**
     * Show the form for creating a new resource.
     */
    public function consultStatusTransactionPix(Request $request)
    {
        return self::consultStatusTransaction($request);
    }
}
