<?php

namespace App\Http\Controllers\Api\WebHook;

use App\Services\Cassinos\Salsa;
use App\Traits\Providers\SalsaGamesTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;

class SalsaController
{
    use SalsaGamesTrait;

    public function salsa(Request $request)
    {
        $xmlstring = $request->getContent();
        $xml = simplexml_load_string($xmlstring, "SimpleXMLElement", LIBXML_NOCDATA);
        $json = json_encode($xml);
        $array = json_decode($json, true);

        $params = $array['Method']['Params'];

        $id = $params['Token']['@attributes']['Value'];
        $return = self::webhookSalsa($request, explode('-', $id)[1]);
        return $return;
    }
}
