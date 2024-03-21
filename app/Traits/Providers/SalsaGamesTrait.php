<?php

namespace App\Traits\Providers;

use App\Helpers\Core as Helper;
use App\Models\Game;
use App\Models\GamesKey;
use App\Models\Order;
use App\Models\User;
use App\Models\Wallet;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

trait SalsaGamesTrait
{

    protected static $baseUrl = '';
    private static $token = '';
    private static $idUser = '';
    private static $pn = '';
    protected static $key = '';
    protected static $data = [];
    protected static $hash = [];

    /**
     * @return void
     */
    public static function getSalsaCredentials(): bool
    {
        $setting = GamesKey::first();

        self::$baseUrl = $setting->getAttributes()['salsa_base_uri'];
        self::$pn = $setting->getAttributes()['salsa_pn'];
        self::$key = $setting->getAttributes()['salsa_key'];

        return true;
    }

    public static function getAllGamesFromDB()
    {

    }

    /**
     * @param $error
     * @return string
     */
    private static function ShowError($method, $error, $errorCode)
    {
        Log::debug("Erro: " . $error);
        $response = "
            <PKT>
                <Result Name='$method' Success='0'>
                    <Returnset>
                        <Error Type='string' Value='$error' />
                        <ErrorCode Type='string' Value='$errorCode' />
                    </Returnset>
                </Result>
            </PKT>
          ";

        if (self::$idUser !== '') {
            return response($response, 200)->header('Content-Type', 'application/xml');
        }
        return $response;
    }

    /**
     * @return string
     */
    public static function generateSalsaToken($game)
    {
        return \Helper::MakeToken([
            'id' => auth('api')->id(),
            'provider' => 'salsa',
            'game' => $game,
            'pn' => self::$pn,
            'time' => time()
        ]);
    }


    /**
     * @param $pn
     * @param $type
     * @param $currency
     * @param $lang
     * @param $game
     * @return string
     */
    public static function playGameSalsa($type, $currency, $lang, $game)
    {
        if (self::getSalsaCredentials()) {
            return self::$baseUrl .
                '?token=' . self::generateSalsaToken($game) .
                '&pn=' . self::$pn .
                '&type=' . $type .
                '&currency=' . $currency .
                '&lang=' . $lang .
                '&game=' . $game;
        }
    }

    /**
     * @param $request
     * @return string|null
     */
    public static function webhookSalsa($request, $idUser = null)
    {
        Log::debug($request->getContent());
        try {
            $xmlstring = $request->getContent();
            //\DB::table('debug')->insert(['text' => json_encode($xmlstring)]);

            $xml = simplexml_load_string($xmlstring, "SimpleXMLElement", LIBXML_NOCDATA);
            $json = json_encode($xml);
            $array = json_decode($json, true);

            $method = $array['Method']['@attributes']['Name'];
            $params = $array['Method']['Params'];

            $host = $request->getHost();
            self::$idUser = $idUser;
            self::$token = $idUser ? $host . '-' . $idUser : $params['Token']['@attributes']['Value'];
            self::$data = json_decode(base64_decode(self::$token), true);

            switch ($method):
                case 'GetAccountDetails':
                    return self::GetAccountDetails($params);
                case 'GetBalance':
                    return self::GetBalance($params);
                case 'PlaceBet':
                    return self::PlaceBet($params);
                case 'AwardWinnings':
                    return self::AwardWinnings($params);
                case 'RefundBet':
                    return self::RefundBet($params);
                case 'ChangeGameToken':
                    return self::ChangeGameToken($params);
                default:
                    return 'nada encontrado.';
            endswitch;
        } catch (\Exception $e) {
            //\DB::table('debug')->insert(['text' => json_encode($e->getMessage())]);
        }
    }

    /**
     * Validate Hash
     * @return bool
     */
    public static function ValidateHash($params, $token)
    {

        if (self::$idUser !== '') {
            return true;
        }
        if (self::getSalsaCredentials()) {
            $hash = $params['Hash']['@attributes']['Value'];
            if ($hash == ':hash') {
                return false;
            }

            $generateHash = self::GenerateHash($token, self::$key);
            if ($hash == $generateHash) {
                return true;
            }

            return false;
        }
    }

    /**
     * Metodo responsavel por gerar o hash
     * @param $paramsValue
     * @param $key
     * @return string
     */
    public static function GenerateHash($paramsValue, $key)
    {
        return hash('sha256', $paramsValue . $key);
    }

    /**
     * @param $params
     * @return string
     */
    public static function GetAccountDetails($params)
    {
        $token = self::$token;
        if (self::ValidateHash($params, $token)) {
            $tokenDec = \Helper::DecToken($token);

            if ($tokenDec['status'] || self::$idUser !== '') {
                $id = self::$idUser ?? $tokenDec['id'];
                $user = User::find($id);
                $wallet = Wallet::where('user_id', $id)->first();
                $currency = $wallet->currency;
                $country = $currency == 'BRL' ? 'BR' : 'USA';

                $response = "
                <PKT>
                    <Result Name='GetAccountDetails' Success='1'>
                        <Returnset>
                            <Token Type='string' Value='$token' />
                            <LoginName Type='string' Value='$user->email' />
                            <Currency Type='string' Value='$currency' />
                            <Country Type='string' Value='$country' />
                            <Birthdate Type='date' Value='1988-08-02' />
                            <Registration Type='date' Value='$user->created_at' />
                            <Gender Type='string' Value='m' />
                        </Returnset>
                    </Result>
                </PKT>
            ";

                //\DB::table('debug')->insert(['text' => json_encode($response)]);
                if (self::$idUser !== '') {
                    return response($response, 200)->header('Content-Type', 'application/xml');
                }
                return $response;
            } else {
                return self::ShowError('GetAccountDetails', 'Error retrieving Token', '1');
            }
        } else {
            return self::ShowError('GetAccountDetails', 'Invalid Hash.', '7000');
        }
    }

    /**
     * Get Account Details
     *
     * @param $params
     * @return string
     */
    public static function GetBalance($params)
    {
        $token = self::$token;
        if (self::ValidateHash($params, $token)) {
            $tokenDec = \Helper::DecToken($token);
            if ($tokenDec['status'] || self::$idUser !== '') {
                $id = self::$idUser ?? $tokenDec['id'];
                $wallet = Wallet::where('user_id', $id)->first();
                $balance = $wallet->total_balance * 100;
                $response = "
                    <PKT>
                        <Result Name='GetBalance' Success='1'>
                            <Returnset>
                                <Token Type='string' Value='$token' />
                                <Balance Type='int' Value='$balance' />
                                <Currency Type='string' Value='$wallet->currency' />
                            </Returnset>
                        </Result>
                    </PKT>
                ";

                //\DB::table('debug')->insert(['text' => json_encode($response)]);
                if (self::$idUser !== '') {
                    return response($response, 200)->header('Content-Type', 'application/xml');
                }
                return $response;
            } else {
                return self::ShowError('GetAccountDetails', 'Error retrieving Token', '1');
            }
        } else {
            return self::ShowError('GetBalance', 'Invalid Hash.', '7000');
        }
    }

    /**
     * Place Bet
     *
     * @param $params
     * @return string
     */
    public static function PlaceBet($params)
    {
        $token = self::$token;
        $transactionID = $params['TransactionID']['@attributes']['Value'];
        $betReferenceNum = $params['BetReferenceNum']['@attributes']['Value'];
        $game = $params['GameReference']['@attributes']['Value'];
        $preparedToken = $transactionID . $betReferenceNum . $token;

        if (self::ValidateHash($params, $preparedToken)) {
            $tokenDec = \Helper::DecToken($token);
            if ($tokenDec['status'] || self::$idUser !== '') {

                $id = self::$idUser ?? $tokenDec['id'];
                $wallet = Wallet::where('user_id', $id)->first();

                if (!empty($wallet)) {

                    $checkTransaction = Order::where('type', 'bet')->where('transaction_id', $transactionID)->first();

                    \Log::info('checkTransaction' . json_encode($checkTransaction));


                    if (!empty($checkTransaction)) {
                        $balanceTotalData = $wallet->total_balance * 100;

                        return "
                            <PKT>
                                <Result Name='PlaceBet' Success='1'>
                                    <Returnset>
                                        <Token Type='string' Value='$token'/>
                                        <Currency Type='string' Value='$wallet->currency'/>
                                        <Balance Type='int' Value='$balanceTotalData'/>
                                        <ExtTransactionID Type='long' Value='$checkTransaction->id'/>
                                        <AlreadyProcessed Type='bool' Value='true'/>
                                    </Returnset>
                                </Result>
                             </PKT>
                            ";
                    }

                    $betAmount = $params['BetAmount']['@attributes']['Value'];
                    $bet = floatval($betAmount / 100);
                    $changeBonus = 'balance';


                    // \Log::info('VALOR APOSTADO: ' .  $betAmount);
                    \Log::info('VALOR APOSTADO: ' . $bet);
                    // \Log::info('TESTE 3.2 ' .  $changeBonus);

                    \Log::info('DADOS' . json_encode($wallet));
                    \Log::info('SALDO REAL' . json_encode($wallet->balance));
                    \Log::info('SALDO BONUS' . json_encode($wallet->balance_bonus));


                    if ($bet > 0) {
                        try {
                            $changeBonus = Helper::updateBalanceBetWithRollover($id, $bet);
                        } catch (\Throwable $exception) {
                            return self::ShowError('PlaceBet', 'Insufficient funds', '6');
                        }
                    }

                    \Log::info('TIPO FINAL: ' . $changeBonus);
                    \Log::info('------------------------------------------------------------');


                    $getWalletBalance = Wallet::where('user_id', $id)->first();
                    $balanceTotal = $getWalletBalance->total_balance * 100;

                    /// cria uma transação
                    ///
                    $transactionId = self::CreateSalsaTransactions($id, $betReferenceNum, $transactionID, 'bet', $changeBonus, $bet, $tokenDec['game'] ?? $game, $tokenDec['pn'] ?? "salsa");

                    if ($transactionId) {
                        $response = "
                            <PKT>
                                <Result Name='PlaceBet' Success='1'>
                                    <Returnset>
                                        <Token Value='$token' />
                                        <Balance Type='int' Value='$balanceTotal' />
                                        <Currency Type='string' Value='$wallet->currency' />
                                        <ExtTransactionID Type='long' Value='$transactionId' />
                                        <AlreadyProcessed Type='bool' Value='false' />
                                    </Returnset>
                                </Result>
                            </PKT>
                        ";

                        //\DB::table('debug')->insert(['text' => json_encode($response)]);
                        if (self::$idUser !== '') {
                            return response($response, 200)->header('Content-Type', 'application/xml');
                        }
                        return $response;
                    } else {
                        return self::ShowError('PlaceBet', 'Transaction not found', '7');
                    }
                } else {
                    return self::ShowError('PlaceBet', 'Wrong data type', '5');
                }
            } else {
                return self::ShowError('PlaceBet', 'Error retrieving Token', '1');
            }
        } else {
            return self::ShowError('PlaceBet', 'Invalid Hash.', '7000');
        }
    }

    /**
     * Create Transactions
     * Metodo para criar uma transação
     *
     * @return false
     */
    private static function CreateSalsaTransactions($playerId, $betReferenceNum, $transactionID, $type, $changeBonus, $amount, $game, $pn)
    {
        $game = Game::where('game_id', $game)->first();
        $order = Order::create([
            'user_id' => $playerId,
            'session_id' => $betReferenceNum,
            'transaction_id' => $transactionID,
            'type' => $type,
            'type_money' => $changeBonus,
            'amount' => $amount,
            'providers' => 'salsa',
            'provider_id' => $game->provider_id,
            'game' => $game,
            'game_uuid' => $pn,
            'round_id' => 1,
        ]);

        \Log::info('Order: ' . $order);

        if ($order) {
            return $order->id;
        }

        return false;
    }

    /**
     * Award Winnings
     *
     * @param $params
     * @return string
     */
    public static function AwardWinnings($params)
    {
        $token = self::$token;
        $transactionID = $params['TransactionID']['@attributes']['Value'];
        $winReferenceNum = $params['WinReferenceNum']['@attributes']['Value'];
        $preparedToken = $transactionID . $winReferenceNum . $token;
        $game = $params['GameReference']['@attributes']['Value'];

        if (self::ValidateHash($params, $preparedToken)) {
            $tokenDec = \Helper::DecToken($token);
            if ($tokenDec['status'] || self::$idUser !== '') {
                $id = self::$idUser ?? $tokenDec['id'];
                $wallet = Wallet::where('user_id', $id)->first();
                $WinAmount = $params['WinAmount']['@attributes']['Value'] / 100;

                $transaction = Order::where('transaction_id', $transactionID)->where('type', 'bet')->first();

                if (!empty($transaction)) {
                    $transactionCreate = false;
                    if ($WinAmount > 0) {
                        Helper::generateGameHistory(
                            $wallet->user_id,
                            'win',
                            $WinAmount,
                            0,
                            $transaction->type_money,
                            $transactionID,
                            true
                        );
                        $transactionCreate = self::CreateSalsaTransactions($id, $winReferenceNum, $transactionID, 'win', $transaction->type_money, $WinAmount, $tokenDec['game'] ?? $game, $tokenDec['pn'] ?? "salsa");
                    }

                    $getWalletBalance = Wallet::where('user_id', $id)->first();
                    $balanceTotal = $getWalletBalance->total_balance * 100;
                    if ($transactionCreate || $WinAmount == 0) {
                        $response = "
                        <PKT>
                            <Result Name='AwardWinnings' Success='1'>
                                <Returnset>
                                    <Token Type='string' Value='$token' />
                                    <Balance Type='int' Value='$balanceTotal' />
                                    <Currency Type='string' Value='$wallet->currency' />
                                    <ExtTransactionID Type='long' Value='$transaction->id' />
                                    <AlreadyProcessed Type='bool' Value='false' />
                                </Returnset>
                            </Result>
                        </PKT>
                    ";

                        if (self::$idUser !== '') {
                            return response($response, 200)->header('Content-Type', 'application/xml');
                        }
                        return $response;
                    } else {
                        return self::ShowError('AwardWinnings', 'Transaction not found', '7');
                    }
                } else {
                    return self::ShowError('AwardWinnings', 'Transaction not found', '7');
                }
            } else {
                return self::ShowError('AwardWinnings', 'Error retrieving Token', '1');
            }
        } else {
            return self::ShowError('AwardWinnings', 'Invalid Hash.', '7000');
        }
    }

    /**
     * Refund Bet
     *
     * @param $params
     * @return string
     */
    public static function RefundBet($params)
    {
        $token = self::$token;
        $transactionID = $params['TransactionID']['@attributes']['Value'];
        $betReferenceNum = $params['BetReferenceNum']['@attributes']['Value'];
        $preparedToken = $transactionID . $betReferenceNum . $token;

        if (self::ValidateHash($params, $preparedToken)) {
            $tokenDec = \Helper::DecToken($token);
            if ($tokenDec['status'] || self::$idUser !== '') {
                $transaction = Order::where('transaction_id', $transactionID)->where('type', 'bet')->where('refunded', 0)->first();
                if (!empty($transaction)) {
                    $refundAmount = $params['RefundAmount']['@attributes']['Value'] / 100;
                    $id = self::$idUser ?? $tokenDec['id'];
                    $wallet = Wallet::where('user_id', $id)->first();

                    /// verificar se é bonus ou balance
                    if ($transaction->type_money == 'balance_bonus') {
                        $wallet->increment('balance_bonus', $refundAmount); /// retorna o valor
                    } else {
                        $wallet->increment('balance', $refundAmount); /// retorna o valor
                    }

                    $transaction->update(['refunded' => 1]); /// define a transação como recusada

                    if (!empty($wallet)) {
                        $id = self::$idUser ?? $tokenDec['id'];
                        $getWalletBalance = Wallet::where('user_id', $id)->lockForUpdate()->first();
                        $balanceTotal = $getWalletBalance->total_balance * 100;

                        $response = "
                            <PKT>
                                <Result Name='RefundBet' Success='1'>
                                    <Returnset>
                                        <Token Type='string' Value='$token' />
                                        <Balance Type='int' Value='$balanceTotal' />
                                        <Currency Type='string' Value='$wallet->currency' />
                                        <ExtTransactionID Type='long' Value='$transaction->id' />
                                        <AlreadyProcessed Type='bool' Value='true' />
                                    </Returnset>
                                </Result>
                            </PKT>
                        ";

                        if (self::$idUser !== '') {
                            return response($response, 200)->header('Content-Type', 'application/xml');
                        }
                        return $response;
                    } else {
                        return self::ShowError('RefundBet', 'Unspecified Error', '6000');
                    }
                } else {
                    return self::ShowError('RefundBet', 'Transaction not found', '7');
                }
            } else {
                return self::ShowError('RefundBet', 'Error retrieving Token', '1');
            }
        } else {
            return self::ShowError('RefundBet', 'Invalid Hash.', '7000');
        }
    }

    /**
     * @param $params
     * @return string
     */
    public static function ChangeGameToken($params)
    {
        $token = self::$token;
        $newGameReference = $params['NewGameReference']['@attributes']['Value'];
        $preparedToken = $newGameReference . $token;

        if (self::ValidateHash($params, $preparedToken)) {
            $tokenDec = \Helper::DecToken($token);
            if ($tokenDec['status'] || self::$idUser !== '') {
                $response = "
                    <PKT>
                        <Result Name='ChangeGameToken' Success='1'>
                            <Returnset>
                                <NewToken Type='string' Value='$token' />
                            </Returnset>
                        </Result>
                    </PKT>
                ";

                if (self::$idUser !== '') {
                    return response($response, 200)->header('Content-Type', 'application/xml');
                }
                return $response;
            } else {
                return self::ShowError('GetAccountDetails', 'Error retrieving Token', '1');
            }
        } else {
            return self::ShowError('ChangeGameToken', 'Invalid Hash.', '7000');
        }
    }
}
