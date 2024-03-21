<?php

namespace Database\Seeders;

use App\Models\Game;
use Exception;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EvoplayNormalizeGames extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $games = $this->games();
        $count = 0;
        foreach ($games as $id => $game) {
            $gameExist = Game::query()
                ->where('provider_id', 6)
                ->where(function ($query) use ($game, $id) {
                    $query->where('game_name', 'like', "%{$game->name}%")
                        ->orWhere('game_code', $game->gameCode)
                        ->orWhere('game_id', $id);

                })
                ->first();
            if ($gameExist === null) {
                continue;
            }
            if ($count < 6) {
                $gameExist->show_home = true;
            }
            $gameExist->game_id = $id;
            $gameExist->distribution = "games_api";
            $gameExist->status = 1;
            $gameExist->save();
            $count++;
        }

        Game::query()
            ->where('provider_id', 6)
            ->where("distribution", '!=', 'games_api')
            ->update(["status" => false]);
    }

    public function games()
    {
        $client = new \GuzzleHttp\Client();

        $key = "8b25d7e6079289eeb83b291ddceca9b0";
        $projectId = 8051;
        $version = 1;
        $signature = $this->getSignature($projectId, $version, [], $key);
        $params["signature"] = $signature;
        $params["version"] = 1;
        $params["project"] = $projectId;

        $urlEvo = "https://api.evoplay.games/Game/getList?";

        $response = $client->request(
            "GET",
            $urlEvo . http_build_query(array_reverse($params))
        );
        $data = json_decode($response->getBody());
        if (!isset($data->data)) {
            throw new Exception('Houve um problema ao buscar os jogos');
        }
        return $data->data;
    }

    public function getSignature($system_id, $version, array $args, $system_key)
    {
        $md5 = array();
        $md5[] = $system_id;
        $md5[] = $version;
        foreach ($args as $required_arg) {
            $arg = $required_arg;
            if (is_array($arg)) {
                if (count($arg)) {
                    $recursive_arg = '';
                    array_walk_recursive($arg, function ($item) use (& $recursive_arg) {
                        if (!is_array($item)) {
                            $recursive_arg .= ($item . ':');
                        }
                    });
                    $md5[] = substr($recursive_arg, 0, strlen($recursive_arg) - 1);
                } else {
                    $md5[] = '';
                }
            } else {
                $md5[] = $arg;
            }
        };
        $md5[] = $system_key;
        $md5_str = implode('*', $md5);
        return md5($md5_str);
    }
}
