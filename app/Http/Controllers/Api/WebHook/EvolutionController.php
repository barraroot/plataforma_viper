<?php


namespace App\Http\Controllers\Api\WebHook;

use App\Services\Cassinos\Evolution;
use Illuminate\Http\Request;

class EvolutionController
{
  public $service;

  public function __construct(
    Evolution $evolution
  )
  {
    $this->service = $evolution;
  }


  public function check(Request $request)
  {
    return $this->service->check($request);
  }

  public function balance(Request $request)
  {
    return $this->service->balance($request);
  }

  public function debit(Request $request)
  {
    return $this->service->debit($request);
  }

  public function credit(Request $request)
  {
    return $this->service->credit($request);
  }

  public function cancel(Request $request)
  {
    return $this->service->cancel($request);
  }

  public function promo_payout(Request $request)
  {
    return $this->service->promo_payout($request);
  }
  public function tipDebit(Request $request)
  {
    return $this->service->tipDebit($request);
  }
  public function tipCancel(Request $request)
  {
    return $this->service->tipCancel($request);
  }
  public function tipClose(Request $request)
  {
    return $this->service->tipClose($request);
  }

  public function teste(Request $request) {
    return $this->service->teste($request);
  }

  public function testeRefound(Request $request)
  {
    
    return $this->service->testeRefound($request);
  }
}
