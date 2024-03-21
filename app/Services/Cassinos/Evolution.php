<?php

namespace App\Services\Cassinos;

use App\Models\GamesHistoric;
use App\Models\SlotsTransaction;
use App\Models\User;
use App\Models\UserIn;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mailgun\Exception;

class Evolution
{

  public $client;

  public function __construct()
  {
    $this->client = new Client();
  }

  public function play(User $user, string $game, int $count = 0)
  {
    try {
      $json = [
        'uuid' => md5(uniqid() . \Carbon\Carbon::now()->timestamp),
        'player' => [
          'id' => (string)$user->id,
          'update' => true,
          'country' => 'BR',
          'language' => 'pt',
          'currency' => 'BRL',
          'session' => [
            'id' => md5(uniqid() . \Carbon\Carbon::now()->timestamp),
            'ip' => request()->ip()
          ]
        ],
        'config' => [
          'game' => [
            'table' => [
              'id' => $game
            ]
          ],
          'channel' => [
            'wrapped' => true
          ]
        ]
      ];

      $response = $this->client->request("POST",
        "https://montesuabancacombr.uat1.evo-test.com/ua/v1/montesuabanca001/test123",
        [
          "headers" => [
            "Content-Type" => "application/json",
            "Authorization" => "Basic bW9udGVzdWFiYW5jYTAwMTp0ZXN0MTIz"
          ],
          "body" => json_encode($json, true)
        ]);
      $game = json_decode($response->getBody()->getContents());
      return [
        "url" => "https://montesuabancacombr.uat1.evo-test.com{$game->entryEmbedded}"
      ];
    } catch (ClientException|RequestException $exception) {
      return $this->play($user, $game, $count + 1);
    } catch (Exception $exception) {
      return $this->play($user, $game, $count + 1);
    }
  }

  public function check(Request $request)
  {
    $user = UserIn::where('id', $request->userId)->lockForUpdate()->first();

    if ($user === null) {
      return response()->json(["status" => "INVALID_PARAMETER"]);
    }

    return response()->json([
      "status" => "OK",
      "uuid" => $request->uuid,
      "sid" => $request->sid,
    ]);
  }

  public function balance(Request $request)
  {
    $user = UserIn::where('id', $request->userId)->lockForUpdate()->first();

    if ($user === null) {
      return response()->json(["status" => "INVALID_PARAMETER"]);
    }

    $data = [
      "status" => "OK",
      "balance" => floatval(number_format($user->user_balance, 2, '.', '')),
      "uuid" => $request->uuid
    ];

    return response()->json($data);

  }

  public function debit(Request $request)
  {
    $user = UserIn::where('id', $request->userId)->lockForUpdate()->first();

    if ($user === null) {
      return response()->json(["status" => "INVALID_PARAMETER"]);
    }

    $slot = SlotsTransaction::query()
      ->where('action_id', $request->transaction["refId"])
      ->where('action', 'bet')
      ->first();

    if ($slot !== null) {
      return response()->json(["status" => "BET_ALREADY_EXIST"]);
    }

    $slot = SlotsTransaction::query()
      ->where('action_id', $request->transaction["refId"])
      ->where('action', 'refund')
      ->first();

    if ($slot !== null) {
      return response()->json(["status" => "FINAL_ERROR_ACTION_FAILED"]);
    }

    if (($user->user_balance - $request->transaction["amount"]) < 0) {
      return response()->json(["status" => "INSUFFICIENT_FUNDS"]);
    }

    $slot = new SlotsTransaction();
    $slot->fill([
      'user_id' => $user->id,
      'game' => $request->game["details"]["table"]["id"],
      'provider' => 'evolution',
      'game_id' => $request->game["details"]["table"]["id"],
      'action' => 'bet',
      'action_id' => $request->transaction["refId"],
      'value' => $request->transaction["amount"],
      'site_id' => $user->site_id
    ]);

    $gameHistory = new GamesHistoric();
    $gameHistory->fill([
      'user_id' => $user->id,
      'cod' => Str::random(12),
      'game_id' => $request->game["details"]["table"]["id"],
      'action_id' => $request->transaction["refId"],
      'value_bet' => $request->transaction["amount"],
      'value_win' => 0,
      'site_id' => $user->site_id
    ]);
    $gameHistory->save();
    $user->user_balance = $user->user_balance - $request->transaction["amount"];
    $user->save();
    $slot->save();

    $data = [
      "status" => "OK",
      "balance" => floatval(number_format($user->user_balance, 2, '.', '')),
      "uuid" => $request->uuid
    ];

    return response()->json($data);
  }

  public function credit(Request $request)
  {
    while (Cache::get($request->userId)) {
      sleep(2);
    }

    Cache::add($request->userId, $request->transaction["amount"], 60);

    $user = UserIn::find($request->userId);


    if ($user === null) {
      Cache::forget($request->userId);
      return response()->json(["status" => "INVALID_PARAMETER"]);
    }

    $slot = SlotsTransaction::where('action_id', $request->transaction["refId"])
      ->where('action', 'bet')
      ->first();

    if ($slot === null) {
      Cache::forget($request->userId);
      return response()->json(["status" => "BET_DOES_NOT_EXIST"]);
    }

    $slot = SlotsTransaction::query()
      ->where('action_id', $request->transaction["refId"])
      ->whereIn('action', ['win', 'refund'])
      ->first();

    if ($slot !== null) {
      Cache::forget($request->userId);
      return response()->json(["status" => "BET_ALREADY_SETTLED"]);
    }

    $slot = new SlotsTransaction();
    $slot->fill([
      'user_id' => $user->id,
      'game' => $request->game["details"]["table"]["id"],
      'provider' => 'evolution',
      'game_id' => $request->game["details"]["table"]["id"],
      'action' => 'win',
      'action_id' => $request->transaction["refId"],
      'value' => $request->transaction["amount"],
      'site_id' => $user->site_id
    ]);

    $gameHistory = GamesHistoric::checkReplicated($request->transaction["refId"]);
    if ($gameHistory === null) {
      $gameHistory = new GamesHistoric();
      $gameHistory->fill([
        'user_id' => $user->id,
        'cod' => Str::random(12),
        'game_id' => $request->game["details"]["table"]["id"],
        'action_id' => $request->transaction["refId"],
        'value_bet' => 0,
        'value_win' => $request->transaction["amount"],
        'site_id' => $user->site_id
      ]);
      $gameHistory->save();
    } else {
      $gameHistory->value_win = $request->transaction["amount"];
      $gameHistory->save();
    }
    $user->user_balance = $user->user_balance + $request->transaction["amount"];
    $user->save();
    Cache::forget($request->userId);

    $slot->save();

    $data = [
      "status" => "OK",
      "balance" => floatval(number_format($user->user_balance, 2, '.', '')),
      "uuid" => $request->uuid
    ];
    return response()->json($data);
  }

  public function cancel(Request $request)
  {

    while (Cache::get($request->userId)) {
      sleep(5);
    }

    Cache::add($request->userId, $request->transaction["amount"], 60);
    $user = UserIn::where('id', $request->userId)->lockForUpdate()->first();

    if ($user === null) {
      Cache::forget($request->userId);
      return response()->json(["status" => "INVALID_PARAMETER"]);
    }

    $slot = SlotsTransaction::query()
      ->where('action_id', $request->transaction["refId"])
      ->where('action', 'bet')
      ->first();

    if ($slot === null) {
      $slot = new SlotsTransaction();
      $slot->fill([
        'user_id' => $user->id,
        'game' => $request->game["details"]["table"]["id"],
        'provider' => 'evolution',
        'game_id' => $request->game["details"]["table"]["id"],
        'action' => 'refund',
        'action_id' => $request->transaction["refId"],
        'value' => $request->transaction["amount"],
        'site_id' => $user->site_id
      ]);

      $slot->save();
      Cache::forget($request->userId);
      return response()->json(["status" => "BET_DOES_NOT_EXIST"]);
    }

    $slot = SlotsTransaction::query()
      ->where('action_id', $request->transaction["refId"])
      ->whereIn('action', ['win', 'refund'])
      ->first();

    if ($slot !== null) {
      Cache::forget($request->userId);
      return response()->json(["status" => "BET_ALREADY_SETTLED"]);
    }

    $slot = new SlotsTransaction();
    $slot->fill([
      'user_id' => $user->id,
      'game' => $request->game["details"]["table"]["id"],
      'provider' => 'evolution',
      'game_id' => $request->game["details"]["table"]["id"],
      'action' => 'refund',
      'action_id' => $request->transaction["refId"],
      'value' => $request->transaction["amount"],
      'site_id' => $user->site_id
    ]);

    $user->user_balance = $user->user_balance + $request->transaction["amount"];
    $user->save();

    Cache::forget($request->userId);
    $slot->save();
    $data = [
      "status" => "OK",
      "balance" => floatval(number_format($user->user_balance, 2, '.', '')),
      "uuid" => $request->uuid
    ];
    return response()->json($data);
  }

  public function promo_payout(Request $request)
  {
    $user = UserIn::where('id', $request->userId)->lockForUpdate()->first();

    if ($user === null) {
      return response()->json(["status" => "INVALID_PARAMETER"]);
    }
    $slot = SlotsTransaction::query()
      ->where('action_id', $request->promoTransaction["id"])
      ->where('action', 'promo')
      ->first();

    if ($slot !== null) {
      return response()->json(["status" => "BET_ALREADY_SETTLED"]);
    }
    $slot = new SlotsTransaction();
    $slot->fill([
      'user_id' => $user->id,
      'game' => 'evolution',
      'provider' => 'evolution',
      'game_id' => 'evolution',
      'action' => 'promo',
      'action_id' => $request->promoTransaction["id"],
      'value' => $request->promoTransaction["amount"],
      'site_id' => $user->site_id
    ]);


    $user->user_balance = $user->user_balance + $request->promoTransaction["amount"];
    $user->save();
    $slot->save();

    $data = [
      "status" => "OK",
      "balance" => floatval(number_format($user->user_balance, 2, '.', '')),
      "uuid" => $request->uuid
    ];

    return response()->json($data);
  }

  public function tipDebit(Request $request)
  {
    $user = UserIn::where('id', $request->userId)->lockForUpdate()->first();

    if ($user === null) {
      return response()->json(["status" => "INVALID_PARAMETER"]);
    }

    $slot = SlotsTransaction::query()
      ->where('action_id', $request->transaction["id"])
      ->where('action', 'tip_debit')
      ->first();
    if ($slot !== null) {
      return response()->json([
        "status" => "BET_ALREADY_EXIST",
        "balance" => floatval(number_format($user->user_balance, 2, '.', '')),
        "bonus" => 0,
        "uuid" => $request->uuid
      ]);
    }

    if (($user->user_balance - $request->transaction["amount"]) < 0) {
      return response()->json(["status" => "INSUFFICIENT_FUNDS"]);
    }

    $slot = new SlotsTransaction();
    $slot->fill([
      'user_id' => $user->id,
      'game' => $request->game["details"]["table"]["id"],
      'provider' => 'evolution',
      'game_id' => $request->game["details"]["table"]["id"],
      'action' => 'tip_debit',
      'action_id' => $request->transaction["id"],
      'value' => $request->transaction["amount"],
      'site_id' => $user->site_id
    ]);


    $gameHistory = new GamesHistoric();
    $gameHistory->fill([
      'user_id' => $user->id,
      'cod' => Str::random(12),
      'game_id' => $request->game_id,
      'action_id' => $request->transaction["refId"],
      'value_bet' => $request->transaction["amount"],
      'value_win' => 0,
      'site_id' => $user->site_id
    ]);
    $gameHistory->save();
    $user->user_balance = $user->user_balance - $request->transaction["amount"];
    $user->save();
    $slot->save();

    $data = [
      "status" => "OK",
      "balance" => floatval(number_format($user->user_balance, 2, '.', '')),
      "bonus" => 0,
      "uuid" => $request->uuid
    ];

    return response()->json($data);
  }

  public function tipCancel(Request $request)
  {
    $user = UserIn::query()
      ->where('id', $request->userId)
      ->lockForUpdate()
      ->first();
    if ($user === null) {
      return response()->json(["status" => "INVALID_PARAMETER"]);
    }

    $slot = SlotsTransaction::query()
      ->where('action_id', $request->transaction["id"])
      ->whereIn('action', ['bet', 'tip_debit', 'refund'])
      ->first();

    if ($slot === null) {
      return response()->json([
        "status" => "BET_ALREADY_EXIST",
        "balance" => floatval(number_format($user->user_balance, 2, '.', '')),
        "bonus" => 0,
        "uuid" => $request->uuid
      ]);
    }

    $slot = SlotsTransaction::query()
      ->where('action_id', $request->transaction["id"])
      ->whereIn('action', ['win', 'refund', 'tip_debit'])
      ->first();

    if ($slot !== null) {
      return response()->json([
        "status" => "BET_ALREADY_SETTLED",
        "balance" => floatval(number_format($user->user_balance, 2, '.', '')),
        "bonus" => 0,
        "uuid" => $request->uuid
      ]);
    }
    $slot = new SlotsTransaction();
    $slot->fill([
      'user_id' => $user->id,
      'game' => $request->game["details"]["table"]["id"],
      'provider' => 'evolution',
      'game_id' => $request->game["details"]["table"]["id"],
      'action' => 'refund',
      'action_id' => $request->transaction["id"],
      'value' => $request->transaction["amount"],
      'site_id' => $user->site_id
    ]);

    $user->user_balance = $user->user_balance + $request->transaction["amount"];
    $user->save();

    $data = [
      "status" => "OK",
      "balance" => floatval(number_format($user->user_balance, 2, '.', '')),
      "bonus" => 0,
      "uuid" => $request->uuid
    ];

    return response()->json($data);
  }

  public function tipClose(Request $request)
  {
    $user = UserIn::where('id', $request->userId)->lockForUpdate()->first();

    $data = [
      "status" => "OK",
      "balance" => floatval(number_format($user->user_balance, 2, '.', '')),
      "bonus" => 0,
      "uuid" => $request->uuid
    ];

    return response()->json($data);
  }

  public function getLiveGames()
  {
    try {
      $response = $this->client->request(
        "GET",
        "https://montesuabancacombr.uat1.evo-test.com/api/lobby/v1/montesuabanca001/state?gameVertical=live,rng&gameType=Roulette&gameProvider=evolution",
        [
          "headers" => [
            "Content-Type" => "application/json",
            "Authorization" => "Basic bW9udGVzdWFiYW5jYTAwMTp0ZXN0MTIz"
          ]
        ]
      );

    } catch (ClientException $exception) {
      return $exception->getMessage();
    }
    return [
      $response->getBody(),
      json_decode($response->getBody())
    ];
  }

  public function teste($request)
  {


    $skipe = true;

    $balance = 0;

    while ($skipe) {


      try {
        $user = UserIn::find($request->id_user);


        $balance = $user->user_balance += $request['amount'];

        DB::beginTransaction();
        $user->user_balance = $balance;


        $user->save();

        DB::commit();

        $skipe = false;

      } catch (\Exception $exception) {

        DB::rollBack();
      }

    }


    return $balance;


  }


  public function testeRefound($request, $bet, $method)
  {
    $body = "{
      'transaction':{
        'id':'{$bet}','refId':'{$bet}','amount':3
      },
      'sid':'e5fff266781ec4a7f201798cd6ee299c',
      'userId':'63',
      'uuid':'352fcf38-4a71-4eeb-afd5-0b01b9aaec56',
      'currency':'BRL',
      'game':{
        'id':'inttesto76ab0ub5mtothm1v',
        'type':'holdem',
        'details':{
          'table':{
            'id':'HoldemTable00001',
            'vid':null
          }
        }
      }
    }";
    $client = new Client();

    $response = $client->request(
      'POST',
      "https://demo.everestgames.com.br/api/{$method}",
      [
        "header" => [
          "Content-Type" => "application/json",
        ],
        "body" => $body
      ]
    );

    return json_decode($response->getBody());
  }
}
