<?php

namespace App\Traits\Providers;

use App\Models\Game;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

trait GamesApi
{
    public function play(Request $request, Game $game)
    {
        $host = $request->getHost();
        try {
            $client = new Client();
            $provider = $game->provider->code;
            $separator = "#";

            $url = "https://api.gerenciadorbet.top";

            if ($provider === "salsa" || $game->provider->id === 19) {
                $separator = '-';
                $url = config('casinos.games_api.url');
            }

            $response = $client->request(
                "POST",
                "$url/games-package",
                [
                    "headers" => [
                        "Content-type" => "application/json"
                    ],
                    "body" => json_encode([
                        "provider" => $provider,
                        "game" => $game->game_id,
                        "userId" => "{$host}{$separator}" . auth('api')->id(),
                        "key" => "$host",
                        "skip" => true
                    ])
                ]
            );

            if ($game->provider->code === 'pg') {
                $r = json_decode($response->getBody(), true);
                return $r["body"];
            }
            $response = json_decode($response->getBody());

            return $response->url;
        } catch (\Throwable $exception) {
            return false;
        }
    }
}

