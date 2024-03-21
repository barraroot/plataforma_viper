<?php

namespace App\Http\Controllers\Api\WebHook;

use App\Http\Controllers\Controller;
use App\Services\Cassinos\Evoplay;
use Illuminate\Http\Request;

class EvoplayController extends Controller
{
  public $service;

  public function __construct(
      Evoplay $evoplayService
  )
  {
      $this->service = $evoplayService;
  }

  public function play(Request $request, string $game)
  {
      return $this->service->play($request->user(), $game);
  }

  public function actions(Request $request)
  {
      return $this->service->actions($request);
  }
}
