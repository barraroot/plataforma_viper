<?php

namespace App\Http\Controllers\Games;

use App\Helpers\Core as Helper;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Games\Contracts\GamesInterface;
use App\Models\Game;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubwayController extends Controller implements GamesInterface
{

    public function getInfo(Request $request): array
    {
        $game = Game::query()
            ->where('game_code', "subway")
            ->first();

        if ($game === null) {
            throw new \Exception('Jogo inválido');
        }

        $order = Order::query()
            ->where('session_id', $request->sessionId)
            ->first();

        return [
            "meta" => $game->meta,
            "bet" => $order->amount,
            "coins" => $game->coins,
            "level" => $game->level,
        ];
    }

    public function win(Request $request): JsonResponse
    {
        /** @var Order $order */
        $order = Order::query()
            ->where('session_id', $request->session_id)
            ->first();

        if ($order === null) {
            return response()->json(["error" => true, "message" => "Ganho sem aposta cadastrada."], 400);
        }

        if ($order->round_id > 1) {
            return response()->json(["error" => true, "message" => "Partida já jogada"], 400);
        }

        $orderWin = Order::query()
            ->where('session_id', $request->session_id)
            ->where('type', 'win')
            ->first();

        if ($orderWin !== null) {
            return response()->json(["error" => true, "message" => "Aposta já foi paga"], 400);
        }

        $user = $order->user()->first();

        Order::create([
            'user_id' => $order->user_id,
            'session_id' => $request->session_id,
            'transaction_id' => $request->session_id,
            'type' => 'win',
            'type_money' => $order->type_money,
            'amount' => $request->amount,
            'providers' => 'Subwaysurfs',
            'game' => $order->game,
            'game_uuid' => $order->game_uuid,
            'round_id' => 1,
        ]);

        Helper::generateGameHistory(
            $user->id,
            'win',
            $request->amount,
            0,
            $order->type_money,
            $request->session_id,
            true
        );

        $win = round(floatval($request->amount), 2);
        return response()->json(["error" => false, "message" => "Parabéns você ganhou  R$ {$win}"]);
    }

    public function loss(Request $request): JsonResponse
    {
        $order = Order::query()
            ->where('session_id', $request->session_id)
            ->first();

        if ($order === null) {
            return response()->json(["error" => true, "message" => "Aposta não encontrada"], 400);
        }

        $order->round_id = 2;
        $order->save();

        return response()->json(["error" => false, "message" => "Para efetuar uma nova aposta clique no jogo novamente"]);
    }
}
