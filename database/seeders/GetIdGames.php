<?php

namespace Database\Seeders;

use App\Models\Game;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class GetIdGames extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $configGames = config('pg_games');

        Game::whereHas('provider', function ($query){
            $query->where('code','PGSOFT');
        })
        ->each(function (Game $game) use($configGames){
            $code = str_replace("_", '-', $game->game_code);
            foreach ($configGames as $configGame){
                if ($configGame["gameName"] == $game->game_name
                || $configGame["gameCode"] == $game->game_code
                || $configGame["gameCode"] == $code
                ){
                    $game->game_id = $configGame["gameId"];

                    $game->distribution = "games_api";

                    $game->save();
                }
            }
        });
    }
}
