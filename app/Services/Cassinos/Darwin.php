<?php

namespace App\Services\Cassinos;


use App\Models\GamesHistoric;
use App\Models\SlotsTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class Darwin
{
    public $url = "";
    public $operatorID = "";

    public function __construct()
    {
        $this->url = "https://eg.paconassa.com/";
        $this->operatorID = "everestgames";
    }
    public function getAccountDetails(Request $request)
    {
        try {
            $token = $request->input('token');
            $id = explode("#", $token);
            $user = User::where("id", $id[0])->lockForUpdate()->first();

            $wallet = $user->wallet()->first();
            $session = md5($id[0] . Carbon::now() . $request->input("gameID"));
            $data = [
                "requestType" => "getaccountdetails",
                "rc" => "0",
                "accountID" => $user->id,
                "sessionID" => $session,
                "balance" => ($wallet->balance * 100),
                "currency" => $request->input("currency"),
                "device" => $request->input("device"),
                "gameID" => $request->input("gameID"),
                "apiVersion" => $request->input("apiVersion")
            ];
            return response()->json($data);
        } catch (\Exception $exception) {
            $data = [
                "requestType" => "getaccountdetails",
                "rc" => "1",
            ];
            return response()->json($data);
        }
    }

    public function getBalance(Request $request)
    {
        try {
            $token = $request->input('token');
            $id = explode("#", $token);
            $user = User::where("id", $id[0])->lockForUpdate()->first();
            $wallet = $user->wallet()->first();
            $data = [
                "requestType" => "getbalance",
                "rc" => "0",
                "accountID" => $user->id,
                "balance" => ($wallet->balance * 100),
                "currency" => $request->input("currency"),
                "apiVersion" => $request->input("apiVersion")
            ];
            return response()->json($data);
        } catch (\Exception $exception) {
            $data = [
                "requestType" => "getbalance",
                "rc" => "1",
            ];
            return response()->json($data);
        }

    }

    public function bet(Request $request)
    {
        try {
            $token = $request->token;
            $id = explode("#", $token);
            $user = User::where("id", $id[0])->lockForUpdate()->first();

            $wallet = $user->wallet()->first();
            $session = md5($token . Carbon::now() . $request->gameID);
            $oldBalance = number_format($wallet->balance * 100, 0, '.', '');
            if (($wallet->balance - ($request->betAmount / 100)) < 0) {
                $data = [
                    "requestType" => "bet",
                    "rc" => "1",
                ];
                return response()->json($data);
            }
            $wallet->balance = (($oldBalance - $request->betAmount) / 100);
            $slot = new SlotsTransaction();
            $slot->fill([
                'user_id' => $user->id,
                'game' => $request->gameID,
                'provider' => 'darwin',
                'game_id' => $request->gameID,
                'action' => 'bet',
                'action_id' => $request->transactionID,
                'value' => ($request->betAmount / 100),
                'site_id' => $user->site_id
            ]);

            $slot->save();


            $gameHistory = new GamesHistoric();
            $gameHistory->fill([
                'user_id' => $user->id,
                'cod' => Str::random(12),
                'game_id' => $request->gameID,
                'action_id' => $request->transactionID,
                'value_bet' => ($request->betAmount / 100),
                'value_win' => 0,
                'site_id' => $user->site_id
            ]);
            $gameHistory->save();

            $wallet->save();

            $data = [
                "requestType" => "bet",
                "rc" => "0",
                "sessionID" => $session,
                "accountID" => $user->id,
                "gameID" => $request->gameID,
                "balance" => $oldBalance - $request->betAmount,
                "currency" => $request->currency,
                "apiVersion" => $request->apiVersion,
                "roundID" => $request->roundID,
                "transactionID" => $request->transactionID
            ];

            return response()->json($data);
        } catch (\Exception $exception) {
            $data = [
                "requestType" => "bet",
                "rc" => "1",
            ];
            return response()->json($data);
        }
    }

    public function rollback(Request $request)
    {
        try {
            $token = $request->token;
            $id = explode("#", $token);
            $user = User::where("id", $id[0])->lockForUpdate()->first();

            $wallet = $user->wallet()->first();
            $session = md5($token . Carbon::now() . $request->gameID);
            $oldBalance = number_format($wallet->balance * 100, 0, '.', '');
            $wallet->balance = (($oldBalance + $request->rollbackAmount) / 100);
            $slot = new SlotsTransaction();
            $slot->fill([
                'user_id' => $user->id,
                'game' => $request->gameID,
                'provider' => 'darwin',
                'game_id' => $request->gameID,
                'action' => 'refund',
                'action_id' => $request->transactionID,
                'value' => ($request->rollbackAmount / 100),
                'site_id' => $user->site_id
            ]);

            $slot->save();
            $wallet->save();

            $data = [
                "requestType" => "rollback",
                "rc" => "0",
                "sessionID" => $session,
                "accountID" => $user->id,
                "gameID" => $request->gameID,
                "balance" => $oldBalance + $request->rollbackAmount,
                "currency" => $request->currency,
                "apiVersion" => $request->apiVersion,
                "roundID" => $request->roundID,
                "transactionID" => $request->transactionID
            ];

            return response()->json($data);
        } catch (\Exception $exception) {
            $data = [
                "requestType" => "rollback",
                "rc" => "1",
            ];
            return response()->json($data);
        }
    }

    public function winnings(Request $request)
    {
        try {

            $token = $request->token;
            $id = explode("#", $token);
            $user = SlotsTransaction::where("id", $id[0])->lockForUpdate()->first();
            $wallet = $user->wallet()->first();
            $session = md5($token . Carbon::now() . $request->gameID);
            $oldBalance = number_format($wallet->balance * 100, 0, '.', '');
            $wallet->balance = (($oldBalance + $request->wonAmount) / 100);
            $slot = new SlotsTransaction();
            $slot->fill([
                'user_id' => $user->id,
                'game' => $request->gameID,
                'provider' => 'darwin',
                'game_id' => $request->gameID,
                'action' => 'win',
                'action_id' => $request->transactionID,
                'value' => ($request->wonAmount / 100),
                'site_id' => $user->site_id
            ]);

            $slot->save();
            $gameHistory = GamesHistoric::where('user_id', $user->id)
                ->where('game_id', $request->gameID)
                ->where('created_at', '>', Carbon::now()
                    ->subMinutes(15)
                    ->format('Y-m-d H:i:s'))
                ->latest()
                ->first();

            if ($gameHistory === null) {
                $gameHistory = new GamesHistoric();
                $gameHistory->fill([
                    'user_id' => $user->id,
                    'cod' => Str::random(12),
                    'game_id' => $request->gameID,
                    'action_id' => $request->transactionID,
                    'value_bet' => 0,
                    'value_win' => ($request->wonAmount / 100),
                    'site_id' => $user->site_id
                ]);
                $gameHistory->save();
            } else {
                $gameHistory->value_win = ($request->wonAmount / 100);
                $gameHistory->save();
            }
            $wallet->save();
            $data = [
                "requestType" => "winnings",
                "rc" => "0",
                "sessionID" => $session,
                "accountID" => $user->id,
                "gameID" => $request->gameID,
                "balance" => $oldBalance + $request->wonAmount,
                "currency" => $request->currency,
                "apiVersion" => $request->apiVersion,
                "roundID" => $request->roundID,
                "transactionID" => $request->transactionID,
            ];
            return response()->json($data);
        } catch (\Exception $exception) {
            $data = [
                "requestType" => "winnings",
                "rc" => "1",
            ];
            return response()->json($data);
        }
    }
}
