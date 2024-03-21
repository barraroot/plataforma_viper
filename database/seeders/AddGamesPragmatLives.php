<?php

namespace Database\Seeders;

use App\Models\Game;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AddGamesPragmatLives extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $games = $this->games();
        foreach ($games as $game) {
            $gameEXist = Game::where('game_id', $game[1])->first();

            if ($gameEXist !== null) {
                $gameEXist->distribution = 'games_api';
                $gameEXist->save();
                continue;
            }

            $categories = [];

            if (Str::contains($game[0], "Roulette")) {
                $categories[] = 21;
            }

            $categories[] = 19;
            $categories[] = 1;

            $gameEvoplay = [
                "game_id" => $game[1],
                "game_name" => $game[0],
                "game_code" => Str::slug($game[0]),
                "provider_id" => 1,
                "status" => 1,
                "distribution" => "games_api"
            ];

            /** @var Game $gameCreate */
            $gameCreate = Game::create($gameEvoplay);

            $gameCreate->categories()->sync($categories);
        }
    }

    public function games()
    {
        return [
            ['VIP Roulette  - The Club ', '545'],
            ['Mega Baccarat', '442'],
            ['Sweet Bonanza CandyLand', '1101'],
            ['Andar Bahar', '1024'],
            ['Dragon Tiger', '1001'],
            ['ONE Blackjack', '901'],
            ['ONE Blackjack 2 - Ruby', '902'],
            ['Dutch ONE Blackjack ', '903'],
            ['Mega Roulette', '204'],
            ['Mega Wheel', '801'],
            ['Blackjack 14 (Green Studio)', '303'],
            ['Brazilian Roulette ', '237'],
            ['Roulette 2', '201'],
            ['Roulette 8 - Indian', '229'],
            ['Roulette 10 - Ruby', '230'],
            ['Baccarat 1', '401'],
            ['Speed Baccarat 1', '402'],
            ['Mega Sic Bo', '701'],
            ['Auto-Roulette 1', '225'],
            ['Speed Roulette 1', '203'],
        ];
    }
}
