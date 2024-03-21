<?php

namespace App\Http\Controllers\Api\WebHook;

use App\Http\Controllers\Controller;
use App\Services\Cassinos\Darwin;
use Illuminate\Http\Request;

class DarwinController extends Controller
{
  public function getAccountDetails(Request $request)
  {
    return app()->make(Darwin::class)->getAccountDetails($request);
  }

  public function getBalance(Request $request)
  {
    return app()->make(Darwin::class)->getBalance($request);
  }

  public function bet(Request $request)
  {
    return app()->make(Darwin::class)->bet($request);
  }

  public function rollback(Request $request)
  {
    return app()->make(Darwin::class)->rollback($request);
  }

  public function winnings(Request $request)
  {
    return app()->make(Darwin::class)->winnings($request);
  }
}
