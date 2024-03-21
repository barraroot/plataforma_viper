<?php

namespace App\Services\Cassinos;

use App\Helpers\Core as Helper;
use App\Models\GamesHistoric;
use App\Models\Order;
use App\Models\SlotsTransaction;
use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class Evoplay
{

    public $client;

    public function __construct()
    {
        $this->client = new Client();
    }

    public function actions(Request $request)
    {

        DB::beginTransaction();

        try {
            Log::debug($request->all());
            $token = $request->token;
            $id = explode("#", $token);
            $user = User::where("id", $id[2])->lockForUpdate()->first();
            $slot = Order::where('transaction_id', $request->callback_id)->first();
            $wallet = $user->wallet()->first();
            /** Essa transação já existe, só retorna o saldo atual */
            if ($slot !== null) {
                DB::commit();
                return response()->json([
                    "status" => "ok",
                    "data" => [
                        "balance" => floatval($wallet->total_balance),
                        "currency" => "BRL",
                        'user_id' => "gampix.vip#gampix.vip#" . $user->id
                    ]
                ]);
            }
            if ($request->name === "bet") {

                $bet = $request->data["amount"];

                try {
                    $changeBonus = Helper::updateBalanceBetWithRollover($user->id, $bet);
                } catch (\Throwable $exception) {
                    return response()->json([
                        "status" => "error",
                        "error" => [
                            "scope" => "user",
                            "no_refund" => "1",
                            'message' => "Not enough money"
                        ]
                    ]);
                }

                if (
                    $slot !== null
                    && $slot->action === 'refund'
                ) {

                    // Confirmar a transação
                    DB::commit();
                    return response()->json([
                        "status" => "ok",
                        "data" => [
                            "balance" => floatval($wallet->total_balance),
                            "currency" => "BRL",
                            'user_id' => "gamepix.vip#gamepix.vip#" . $user->id
                        ]
                    ]);
                }
                $details = json_decode($request->data["details"]);
                Order::create([
                    'user_id' => $user->id,
                    'session_id' => $request->callback_id,
                    'transaction_id' => $request->callback_id,
                    'type' => 'bet',
                    'type_money' => $changeBonus ?? 'balance',
                    'amount' => $request->data["amount"],
                    'providers' => 'evoplay',
                    'game' => $details->game->game_id,
                    'game_uuid' => $details->game->game_id,
                    'round_id' => 1,
                ]);

                Helper::generateGameHistory(
                    $wallet->user_id,
                    'loss',
                    0,
                    $request->data["amount"],
                    $changeBonus,
                    $request->callback_id
                );
            }

            if ($request->name === "win") {

                $details = json_decode($request->data["details"]);
                Order::create([
                    'user_id' => $user->id,
                    'session_id' => $request->callback_id,
                    'transaction_id' => $request->callback_id,
                    'type' => 'win',
                    'type_money' => 'balance',
                    'amount' => $request->data["amount"],
                    'providers' => 'evoplay',
                    'game' => $details->game->game_id,
                    'game_uuid' => $details->game->game_id,
                    'round_id' => 1,
                ]);

                $lastOrder = Order::where('user_id', $user->id)->latest()->first();
                $changeBonus = $lastOrder?->type_money ?? "balance";

                Helper::generateGameHistory(
                    $wallet->user_id,
                    'win',
                    $request->data["amount"],
                    0,
                    $changeBonus,
                    $request->transaction_id
                );
            }

            if ($request->name === "refund") {
                $slot = Order::where('transaction_id', $request->data["refund_callback_id"])->first();
                $slotRefund = Order::where('transaction_id', $request->data["refund_callback_id"])->where('action', 'refund')->first();
                if (
                    $slot === null
                    || $slotRefund !== null
                ) {

                    // Confirmar a transação
                    DB::commit();
                    return response()->json([
                        "status" => "ok",
                        "data" => [
                            "balance" => floatval($wallet->balance),
                            "currency" => "BRL",
                            'user_id' => "gamepix.vip#gamepix.vip#" . $user->id
                        ]
                    ]);
                }
                $details = json_decode($request->data["details"]);

                Order::create([
                    'user_id' => $user->id,
                    'session_id' => $request->data["refund_callback_id"],
                    'transaction_id' => $request->data["refund_callback_id"],
                    'type' => 'refund',
                    'type_money' => 'balance',
                    'amount' => $request->data["amount"],
                    'providers' => 'evoplay',
                    'game' => $details->game->game_id,
                    'game_uuid' => $details->game->game_id,
                    'round_id' => 1,
                ]);

                $lastOrder = Order::where('user_id', $user->id)->latest()->first();

                $changeBonus = $lastOrder?->type_money ?? "balance";

                Helper::payWithRollover(
                    $user->id,
                    $changeBonus,
                    $request->data["amount"]
                );
            }

            // Confirmar a transação
            DB::commit();

            return response()->json([
                "status" => "ok",
                "data" => [
                    "balance" => floatval($wallet->total_balance),
                    "currency" => "BRL",
                    'user_id' => "gampix.vip#gampix.vip#" . $user->id
                ]
            ]);
        } catch (\Exception $e) {
            // Caso ocorra algum erro, desfazer a transação
            DB::rollback();
            Log::debug($e->getMessage(), [
                "data" => $request->all()
            ]);
            // Retornar uma resposta de erro
            return response()->json([
                "status" => "error",
                "error" => [
                    "scope" => "user",
                    "no_refund" => "1",
                    'message' => "Request falied"
                ]
            ]);
        }
    }

}
