<?php

namespace App\Http\Controllers\Games\Contracts;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

interface GamesInterface
{
    public function getInfo(Request $request): array;

    public function win(Request $request): JsonResponse;

    public function loss(Request $request): JsonResponse;
}
