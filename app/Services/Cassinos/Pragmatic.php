<?php

namespace App\Services\Cassinos;

use App\Models\Game;
use App\Models\User;
use App\Helpers\Core as Helper;
use Carbon\Carbon;
use App\Models\Order;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class Pragmatic
{
    public $client;

    public function __construct(
        Client $client
    )
    {
        $this->client = $client;
    }

    public function authenticate(Request $r)
    {
        DB::beginTransaction();
        try {
            $token = $r->token;
            $id = explode("#", $token)[2];

            $user = User::whereRaw('CAST(id AS CHAR) = ?', [$id])->first();

            if ($user === null) {
                return response()->json(["error" => 1, "description" => "User not found"]);
            }

            $wallet = $user->wallet()->first();
            $data = [
                "userId" => $r->token,
                "currency" => "BRL",
                "jurisdiction" => 99,
                "country" => "BR",
                "cash" => floatval($wallet->total_balance),
                "bonus" => 0,
                "error" => 0,
                "description" => "Success"
            ];
            Log::debug(json_encode($data));
            DB::commit();
            return response()->json($data);

        } catch (\Exception $exception) {
            DB::rollBack();
            Log::debug($exception->getMessage());
        }
    }

    public function balance(Request $r)
    {
        $token = $r->token ?? $r->userId;
        $id = explode("#", $token)[2];
        $user = User::whereRaw('CAST(id AS CHAR) = ?', [$id])->first();

        if ($user === null) {
            return response()->json(["error" => 1, "description" => "User not found"]);
        }

        $wallet = $user->wallet()->first();
        $data = [
            "currency" => "BRL",
            "cash" => floatval($wallet->total_balance),
            "bonus" => 0,
            "error" => 0,
            "description" => "Success"
        ];

        return response()->json($data);
    }

    public function bet(Request $request)
    {
        DB::beginTransaction();
        try {
            $slot = Order::where('transaction_id', $request->reference)->first();
            if ($slot !== null) {
                $user = User::find($slot->user_id);

                $wallet = $user->wallet()->first();
                $data = [
                    "transactionId" => $request->reference,
                    "currency" => "BRL",
                    "cash" => floatval($wallet->total_balance),
                    "bonus" => 0,
                    "usedPromo" => 0,
                    "error" => 0,
                    "description" => "Success"
                ];
                return response()->json($data);
            }

            $token = $request->token ?? $request->userId;
            $id = explode("#", $token)[2];
            $user = User::whereRaw('CAST(id AS CHAR) = ?', [$id])->first();
            $bet = $request->amount;
            $wallet = $user->wallet()->first();


            try {
                $changeBonus = Helper::updateBalanceBetWithRollover($id, $bet);
            } catch (\Throwable $exception) {
                $data = [
                    "transactionId" => $request->reference,
                    "currency" => "BRL",
                    "cash" => floatval($wallet->balance),
                    "bonus" => 0,
                    "usedPromo" => 0,
                    "error" => 1,
                    "description" => "User has no funds"
                ];

                return response()->json($data);
            }

            Log::debug($changeBonus);

            Helper::generateGameHistory(
                $wallet->user_id,
                'loss',
                0,
                $request->amount,
                $changeBonus,
                $request->reference
            );

            $game = Game::query()
                ->where('game_id', $request->gameId)
                ->first();

            $order = Order::create([
                'user_id' => $user->id,
                'session_id' => $request->reference,
                'transaction_id' => $request->reference,
                'type' => 'loss',
                'type_money' => $changeBonus ?? 'balance',
                'amount' => $request->amount,
                'providers' => 'PRAGMATIC',
                'game' => $game?->name ?? $request->gameId,
                'game_uuid' => $request->gameId,
                'round_id' => 1,
            ]);

            $data = [
                "transactionId" => $request->reference,
                "currency" => "BRL",
                "cash" => floatval($wallet->total_balance),
                "bonus" => 0,
                "usedPromo" => 0,
                "error" => 0,
                "description" => "Success"
            ];

            DB::commit();
            return response()->json($data);

        } catch (\Exception $exception) {
            DB::rollBack();
            Log::debug($exception->getMessage());
        }
    }

    public function refound(Request $request)
    {
        DB::beginTransaction();
        try {
            $action_rollback = Order::where('transaction_id', $request->reference)->first();
            $token = $request->token ?? $request->userId;
            $id = explode("#", $token)[2];
            $user = User::whereRaw('CAST(id AS CHAR) = ?', [$id])->first();

            if ($action_rollback === null) {
                $data = [
                    "transactionId" => $request->reference,
                    "error" => 0,
                    "description" => "Reference not found"
                ];
                return response()->json($data);
            }

            $checkExistRefund = Order::query()
                ->where("transaction_id", $request->reference)
                ->where("action", "refund")
                ->first();


            if ($checkExistRefund !== null) {
                $data = [
                    "transactionId" => $request->reference,
                    "error" => 0,
                    "description" => "Success"
                ];
                return response()->json($data);
            }

            Order::create([
                'user_id' => $user->id,
                'session_id' => $request->reference,
                'transaction_id' => $request->reference,
                'type' => 'refund',
                'type_money' => $changeBonus ?? 'balance',
                'amount' => $action_rollback->amount,
                'providers' => 'PRAGMATIC',
                'game' => $request->gameId,
                'game_uuid' => $request->gameId,
                'round_id' => 1,
            ]);

            if ($action_rollback->type_money == 'balance_bonus') {
                $wallet->increment('balance_bonus', $action_rollback->amount);
            } else {
                /// pagando o ganhos
                $wallet->increment('balance', $action_rollback->amount);
            }

            $data = [
                "transactionId" => $request->reference,
                "error" => 0,
                "description" => "Success"
            ];

            DB::commit();
            return response()->json($data);

        } catch (\Exception $exception) {
            DB::rollBack();
        }
    }

    public function adjustment(Request $request)
    {
        DB::beginTransaction();
        try {
            $token = $request->token ?? $request->userId;
            $id = explode("#", $token)[2];
            $user = User::whereRaw('CAST(id AS CHAR) = ?', [$id])->first();
            $wallet = $user->wallet()->first();
            $checkExistRefund = Order::query()
                ->where("transaction_id", $request->reference)
                ->where("type", "adjustment")
                ->first();


            if ($checkExistRefund !== null) {
                $data = [
                    "transactionId" => $request->reference,
                    "currency" => "BRL",
                    "cash" => floatval($wallet->total_balance),
                    "bonus" => 0,
                    "error" => 0,
                    "description" => "Success"
                ];
                return response()->json($data);
            }

            if (0 > ($wallet->total_balance + $request->amount)) {
                $data = [
                    "error" => 1,
                    "description" => "User has no funds"
                ];

                return response()->json($data);

            }
            Order::create([
                'user_id' => $user->id,
                'session_id' => $request->reference,
                'transaction_id' => $request->reference,
                'type' => 'adjustment',
                'type_money' => $changeBonus ?? 'balance',
                'amount' => $action_rollback->amount,
                'providers' => 'PRAGMATIC',
                'game' => $request->gameId,
                'game_uuid' => $request->gameId,
                'round_id' => 1,
            ]);

            $wallet->increment('balance', $WinAmount);

            $data = [
                "transactionId" => $request->reference,
                "currency" => "BRL",
                "cash" => floatval($wallet->total_balance),
                "bonus" => 0,
                "error" => 0,
                "description" => "Success"
            ];

            DB::commit();
            return response()->json($data);

        } catch (\Exception $exception) {
            DB::rollBack();
        }


    }

    public function result(Request $request)
    {
        DB::beginTransaction();
        try {
            $slot = Order::where('transaction_id', $request->reference)->first();

            if ($slot !== null) {
                $user = User::find($slot->user_id);
                $wallet = $user->wallet()->first();
                $data = [
                    "transactionId" => $request->reference,
                    "currency" => "BRL",
                    "cash" => floatval($wallet->total_balance),
                    "bonus" => 0,
                    "error" => 0,
                    "description" => "Success"
                ];
                return response()->json($data);
            }

            $token = $request->token ?? $request->userId;
            $id = explode("#", $token)[2];
            $user = User::whereRaw('CAST(id AS CHAR) = ?', [$id])->first();
            $wallet = $user->wallet()->first();
            $lastOrder = Order::where('user_id', $user->id)->latest()->first();
            $changeBonus = $lastOrder?->type_money ?? "balance";

            $game = Game::query()
                ->where('game_id', $request->gameId)
                ->first();

            Order::create([
                'user_id' => $user->id,
                'session_id' => $request->reference,
                'transaction_id' => $request->reference,
                'type' => 'win',
                'type_money' => $changeBonus,
                'amount' => $request->amount,
                'providers' => 'PRAGMATIC',
                'game' => $game?->name ?? $request->gameId,
                'game_uuid' => $request->gameId,
                'round_id' => 1,
            ]);

            Helper::generateGameHistory(
                $wallet->user_id,
                'win',
                $request->amount,
                0,
                $changeBonus,
                $request->reference
            );


            $data = [
                "transactionId" => $request->reference,
                "currency" => "BRL",
                "cash" => floatval($wallet->total_balance),
                "bonus" => 0,
                "error" => 0,
                "description" => "Success"
            ];

            DB::commit();
            return response()->json($data);

        } catch (\Exception $exception) {
            DB::rollBack();

            $data = [
                "error" => 1,
                "description" => $exception->getMessage()
            ];

            return response()->json($data);
        }
    }

    public function bonusWin(Request $request)
    {
        DB::beginTransaction();
        try {
            $token = $request->token;
            $id = explode("#", $token)[2];
            $user = User::whereRaw('CAST(id AS CHAR) = ?', [$id])->first();
            $wallet = $user->wallet()->first();
            $action_rollback = SlotsTransaction::checkReplicated($request->reference);
            if ($action_rollback !== null) {
                $data = [
                    "transactionId" => $request->reference,
                    "currency" => "BRL",
                    "cash" => floatval($wallet->balance),
                    "bonus" => 0,
                    "error" => 0,
                    "description" => "Success"
                ];

                return response()->json($data);
            }

            $slot = new SlotsTransaction();

            $slot->fill([
                'game' => 'pragmatic-bonus',
                'game_id' => "pragmatic-bonus",
                'action' => 'bonus',
                'action_id' => $request->reference,
                'user_id' => $user->id,
                'provider' => 'pragmatic',
                'value' => $request->amount,
                'site_id' => $user->site_id
            ]);

            $slot->save();

            $data = [
                "transactionId" => $request->reference,
                "currency" => "BRL",
                "cash" => floatval($wallet->balance),
                "bonus" => 0,
                "error" => 0,
                "description" => "Success"
            ];

            DB::commit();
            return response()->json($data);

        } catch (\Exception $exception) {
            DB::rollBack();
            return response()->json([
                "message" => $exception->getMessage()
            ]);
        }
    }

    public function jackpotWin(Request $request)
    {
        DB::beginTransaction();
        try {
            $token = $request->token;
            $id = explode("#", $token)[2];
            $user = User::whereRaw('CAST(id AS CHAR) = ?', [$id])->first();
            $wallet = $user->wallet()->first();
            $action_rollback = SlotsTransaction::checkReplicated($request->reference);
            if ($action_rollback !== null) {
                $data = [
                    "transactionId" => $request->reference,
                    "currency" => "BRL",
                    "cash" => floatval($wallet->balance),
                    "bonus" => 0,
                    "error" => 0,
                    "description" => "Success"
                ];

                return response()->json($data);
            }

            $slot = new SlotsTransaction();

            $slot->fill([
                'game' => $request->providerId,
                'game_id' => $request->gameId,
                'action' => 'jackpots',
                'action_id' => $request->reference,
                'user_id' => $user->id,
                'provider' => 'pragmatic',
                'value' => $request->amount,
                'site_id' => $user->site_id
            ]);

            $wallet->balance = floatval($wallet->balance + $request->amount);
            $wallet->save();
            $slot->save();

            $data = [
                "transactionId" => $request->reference,
                "currency" => "BRL",
                "cash" => floatval($wallet->balance),
                "bonus" => 0,
                "error" => 0,
                "description" => "Success"
            ];
            DB::commit();
            return response()->json($data);

        } catch (\Exception $exception) {
            DB::rollBack();
        }
    }

    public function promoWin(Request $request)
    {
        DB::beginTransaction();
        try {
            $token = $request->token;
            $id = explode("#", $token)[2];
            $user = User::whereRaw('CAST(id AS CHAR) = ?', [$id])->first();
            $wallet = $user->wallet()->first();
            $action_rollback = SlotsTransaction::checkReplicated($request->reference);
            if ($action_rollback !== null) {
                $data = [
                    "transactionId" => $request->reference,
                    "currency" => "BRL",
                    "cash" => floatval($wallet->balance),
                    "bonus" => 0,
                    "error" => 0,
                    "description" => "Success"
                ];

                return response()->json($data);
            }

            $slot = new SlotsTransaction();

            $slot->fill([
                'game' => $request->providerId,
                'game_id' => $request->gameId ?? 'campaign',
                'action' => 'promoWin',
                'action_id' => $request->reference,
                'user_id' => $user->id,
                'provider' => 'pragmatic',
                'value' => $request->amount,
                'site_id' => $user->site_id
            ]);

            $wallet->balance = floatval($wallet->balance + $request->amount);
            $wallet->save();
            $slot->save();

            $data = [
                "transactionId" => $request->reference,
                "currency" => "BRL",
                "cash" => floatval($wallet->balance),
                "bonus" => 0,
                "error" => 0,
                "description" => "Success"
            ];

            DB::commit();
            return response()->json($data);

        } catch (\Exception $exception) {
            DB::rollBack();
        }
    }
}
