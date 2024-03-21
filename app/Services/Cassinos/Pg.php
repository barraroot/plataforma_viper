<?php

namespace App\Services\Cassinos;

use App\Helpers\Core as Helper;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class Pg
{
    public $op = "";
    public $sk = "";
    public $url = "";

    public function __construct()
    {
        $this->op = '8F7E169D-289E-4207-936C-2E4FFAC8FDE4';
        $this->sk = 'DD258C30D5BE4DAE9CCE76274E2D425F';
        $this->url = config('casinos.pg.url');
    }

    public function session(Request $request)
    {
        Log::debug($request->all());
        $token = $request->operator_player_session;
        if (!Str::contains($token, '#')) {
            $token = urldecode($token);
        }
        $id = explode("#", $token)[2];
        $user = User::whereRaw('CAST(id AS CHAR) = ?', [$id])->first();

        if ($user === null) {
            return response()->json(["data" => null, "error" => [
                "code" => "1034",
                "message" => "Invalid request"
            ]], 200);
        }


        if (
            $request->operator_token != $this->op
            || $request->secret_key != $this->sk
        ) {
            return response()->json(["data" => null, "error" => [
                "code" => "1034",
                "message" => "Invalid request"
            ]], 200);
        }


        $data = [
            "player_name" => $user->id . '-' . $user->id,
            "nickname" => (string)$user->id,
            "currency" => "BRL"
        ];

        return response()->json(["error" => null, "data" => $data]);
    }

    public function cashGet(Request $request)
    {

        $token = $request->operator_player_session;
        if (!Str::contains($token, '#')) {
            $token = urldecode($token);
        }
        $id = explode("#", $token)[2];
        $user = User::whereRaw('CAST(id AS CHAR) = ?', [$id])->first();
        if ($user === null) {
            return response()->json(["data" => null, "error" => [
                "code" => "1034",
                "message" => "Invalid request"
            ]], 200);
        }


        $wallet = $user->wallet()->first();

        Log::debug($wallet->toArray());
        if (
            $request->operator_token != $this->op
            || $request->secret_key != $this->sk
        ) {
            return response()->json(["data" => null, "error" => [
                "code" => "1034",
                "message" => "Invalid request"
            ]], 200);
        }

        $userInfo = explode('-', $request->player_name);
        if ($user->id !== (int)$userInfo[1]) {
            return response()->json(["data" => null, "error" => [
                "code" => "1034",
                "message" => "Invalid request"
            ]], 200);
        }

        $data = [
            "updated_time" => Carbon::now()->timestamp * 1000,
            "balance_amount" => floatval(number_format($wallet->total_balance, 2, '.', '')),
            "currency_code" => "BRL",
        ];

        return response()->json(["error" => null, "data" => $data], 200);
    }

    public function getTransfer(Request $request)
    {
        $userInfo = explode('-', $request->player_name);
        $token = urldecode($request->operator_player_session);
        if (!Str::contains($token, '#')) {
            $token = urldecode($token);
        }
        $token = explode("#", $token);
        if ($request->operator_player_session === null) {
            $user = User::whereRaw('CAST(id AS CHAR) = ?', [$userInfo[1]])->where('id', $userInfo[1])->lockForUpdate()->first();
        } else {
            $user = User::whereRaw('CAST(id AS CHAR) = ?', [$token[2]])->lockForUpdate()->first();
        }

        if ($user === null) {
            return response()->json(["data" => null, "error" => [
                "code" => "3004",
                "message" => "Player does not exist"
            ]], 200);
        }


        if (
            $request->operator_token != $this->op
            || $request->secret_key != $this->sk
        ) {
            return response()->json(["data" => null, "error" => [
                "code" => "1034",
                "message" => "Invalid request"
            ]], 200);
        }


        $slot = Order::where('transaction_id', $request->transaction_id)->first();

        if ($slot !== null) {
            $user = User::find($slot->user_id);
            $wallet = $user->wallet()->first();
            $data = [
                "updated_time" => (integer)($request->updated_time ?? $slot->updated_time),
                "balance_amount" => floatval(number_format($wallet->total_balance, 2, '.', '')),
                "currency_code" => "BRL",
            ];
            return response()->json(["error" => null, "data" => $data], 200);
        }

        if ($user->id !== (int)$userInfo[1]) {
            return response()->json(["data" => null, "error" => [
                "code" => "3004",
                "message" => "Player does not exist"
            ]], 200);
        }

        if ($request->currency_code !== "BRL") {
            Log::debug('BRL');
            return response()->json(["data" => null, "error" => [
                "code" => "1034",
                "message" => "Player does not exist"
            ]], 200);
        }
        $changeBonus = null;
        $bet = $request->bet_amount;

        try {
            $changeBonus = Helper::updateBalanceBetWithRollover($user->id, $bet);
        } catch (\Throwable $exception) {
            return response()->json(["data" => null, "error" => [
                "code" => "3202",
                "message" => "Insufficient balance"
            ]], 200);
        }

        if ($request->is_adjustment != 'True' || $request->is_validate_bet != 'True') {

            $WinAmount = $request->win_amount;

            Helper::generateGameHistory(
                $user->id,
                'win',
                $WinAmount,
                0,
                $changeBonus,
                $request->transaction_id
            );
        }

        Order::create([
            'user_id' => $user->id,
            'session_id' => $request->updated_time,
            'transaction_id' => $request->transaction_id,
            'type' => 'bet',
            'type_money' => $changeBonus ?? 'balance',
            'amount' => $request->bet_amount,
            'providers' => 'PGSOFT',
            'game' => $request->game_id,
            'game_uuid' => $request->game_id,
            'round_id' => 1,
        ]);

        Helper::generateGameHistory(
            $user->id,
            'loss',
            0,
            $request->bet_amount,
            $changeBonus,
            $request->transaction_id
        );

        Order::create([
            'user_id' => $user->id,
            'session_id' => $request->updated_time,
            'transaction_id' => $request->transaction_id,
            'type' => 'win',
            'type_money' => $changeBonus ?? 'balance',
            'amount' => $request->win_amount,
            'providers' => 'PGSOFT',
            'game' => $request->game_id,
            'game_uuid' => $request->game_id,
            'round_id' => 1,
        ]);

        $wallet = $user->wallet()->first();

        $data = [
            "updated_time" => (integer)$request->updated_time,
            "balance_amount" => floatval(number_format($wallet->total_balance, 2, '.', '')),
            "currency_code" => "BRL",
        ];
        return response()->json(["error" => null, "data" => $data], 200);
    }

    public function adjust(Request $request)
    {
        $slot = Order::where('transaction_id', $request->adjustment_transaction_id)->first();

        if ($slot !== null) {
            $user = User::find($slot->user_id);
            $wallet = $user->wallet()->first();
            $old_balance = number_format($wallet->balance - $request->transfer_amount, 2, '.', '');
            $data = [
                "updated_time" => (integer)$request->adjustment_time,
                "balance_after" => (float)number_format($wallet->total_balance, 2, '.', ''),
                "balance_before" => (float)$old_balance,
                "adjust_amount" => (float)$request->transfer_amount,
            ];
            return response()->json(["error" => null, "data" => $data], 200);
        }

        $userInfo = $request->operator_player_session;
        if (!Str::contains($userInfo, '#')) {
            $userInfo = urldecode($userInfo);
        }
        if ($request->operator_player_session === null) {
            $userInfo = explode('-', $request->player_name);
            $user = User::whereRaw('CAST(id AS CHAR) = ?', [$userInfo[1]])->where('id', $userInfo[0])->lockForUpdate()->first();
        } else {
            $token = explode("#", urldecode($request->operator_player_session))[2];
            $user = User::whereRaw('CAST(id AS CHAR) = ?', [$token])->lockForUpdate()->first();
        }

        if ($user === null) {
            return response()->json(["data" => null, "error" => [
                "code" => "3004",
                "message" => "Player does not exist"
            ]], 200);
        }
        $wallet = $user->wallet()->first();
        if (
            $request->operator_token != $this->op
            || $request->secret_key != $this->sk
        ) {
            return response()->json(["data" => null, "error" => [
                "code" => "1034",
                "message" => "Invalid request"
            ]], 200);
        }


        if ($user->id !== (int)$userInfo[1]) {
            return response()->json(["data" => null, "error" => [
                "code" => "3004",
                "message" => "Player does not exist"
            ]], 200);
        }

        if ($request->currency_code !== "BRL") {
            return response()->json(["data" => null, "error" => [
                "code" => "1034",
                "message" => "Player does not exist"
            ]], 200);
        }

        $old_balance = number_format($wallet->balance, 2, '.', '');
        if ($request->transfer_amount >= 0) {
            $wallet->increment("balance", $request->transfer_amount);
        }
        if ($request->transfer_amount < 0) {
            $wallet->decrement("balance", ($request->transfer_amount * -1));
        }

        Order::create([
            'user_id' => $user->id,
            'session_id' => $request->updated_time,
            'transaction_id' => $request->adjustment_transaction_id,
            'type' => 'redund',
            'type_money' => 'balance',
            'amount' => $request->bet_amount,
            'providers' => 'adjust',
            'game' => $request->game_id,
            'game_uuid' => $request->game_id,
            'round_id' => 1,
        ]);
        $data = [
            "updated_time" => (integer)$request->adjustment_time,
            "balance_after" => (float)number_format($wallet->total_balance, 2, '.', ''),
            "balance_before" => (float)$old_balance,
            "adjust_amount" => (float)$request->transfer_amount,
        ];

        return response()->json(["error" => null, "data" => $data], 200);
    }
}
