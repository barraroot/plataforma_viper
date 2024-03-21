<?php

namespace App\Http\Controllers\Api\WebHook;

use App\Http\Controllers\Controller;
use App\Models\SlotsTransactions;
use App\Models\UserIn;
use App\Services\Cassinos\Pragmatic;
use Illuminate\Http\Request;

class PragmaticController extends Controller
{
  public function authenticate(Request $request)
  {
    return app()->make(Pragmatic::class)->authenticate($request);

  }

  public function balance(Request $request)
  {
    return app()->make(Pragmatic::class)->balance($request);
  }

  public function bet(Request $request)
  {
    return app()->make(Pragmatic::class)->bet($request);
  }

  public function refound(Request $request)
  {
    return app()->make(Pragmatic::class)->refound($request);
  }

  public function adjustment(Request $request)
  {
    return app()->make(Pragmatic::class)->adjustment($request);
  }

  public function result(Request $request)
  {
    return app()->make(Pragmatic::class)->result($request);
  }

  public function bonusWin(Request $request)
  {
    return app()->make(Pragmatic::class)->bonusWin($request);
  }

  public function jackpotWin(Request $request)
  {
    return app()->make(Pragmatic::class)->jackpotWin($request);
  }

  public function promoWin(Request $request)
  {
    return app()->make(Pragmatic::class)->promoWin($request);
  }
}
