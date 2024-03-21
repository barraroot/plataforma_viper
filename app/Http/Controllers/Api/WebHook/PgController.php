<?php


namespace App\Http\Controllers\Api\WebHook;

use App\Http\Controllers\Controller;
use App\Services\Cassinos\Pg;
use Illuminate\Http\Request;

class PgController extends Controller
{
  public function session(Request $request)
  {
    return app()->make(Pg::class)->session($request);
  }

  public function cashGet(Request $request)
  {
    return app()->make(Pg::class)->cashGet($request);
  }

  public function getTransfer(Request $request)
  {
    return app()->make(Pg::class)->getTransfer($request);
  }
  public function adjust(Request $request)
  {
    return app()->make(Pg::class)->adjust($request);
  }
}
