<?php

namespace App\Services\Cassinos;

use App\Models\GamesHistoric;
use App\Models\SlotsTransaction;
use App\Models\User;
use App\Models\UserIn;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use SimpleXMLElement;

class Salsa
{
    public $url = "";
    public $pn = "";

    public function __construct()
    {
        $this->url = config('casinos.salsa.url');
        $this->pn = config('casinos.salsa.pn');
    }

    public function play(User $user, $game)
    {
        return [
            "url" => "$this->url/game?token={$user->id}&pn={$this->pn}&lang=pt&game={$game}"
        ];
    }

    public function salsa(Request $request)
    {
        Log::debug("chegou");
        $info = $this->xmlToArray($request->getContent());
        $token = urldecode($info['token']);
        $id = explode("#", $token)[2];
        $user = User::whereRaw('CAST(id AS CHAR) = ?', [$id])->first();
        $user = $user[0] ?? null;
        $success = 1;
        if ($user === null) {
            $success = 0;
        }

        $xml = simplexml_load_string($request->getContent());


        if ($xml->Method->Params->Hash["Value"] == ':hash') {

            $data = [
                "Result" => [
                    '@attributes' => [
                        'Name' => $xml->Method->Name,
                        'Success' => 0
                    ],
                    'Returnset' => [
                        'Error' => "Invalid Hash",
                        'ErrorCode' => 7000
                    ]
                ]
            ];

            $responseXml = $this->arrayToXml($data);

            return response($responseXml, 200)->header('Content-Type', 'text/xml');
        }
        if ($info["method"] == "GetAccountDetails") {

            if ($user === null) {
                $data = [
                    "Result" => [
                        '@attributes' => [
                            'Name' => "GetAccountDetails",
                            'Success' => 0
                        ],
                        'Returnset' => [
                            'Error' => "Error retrieving Token",
                            'ErrorCode' => 1
                        ]
                    ]
                ];

                $responseXml = $this->arrayToXml($data);

                return response($responseXml, 200)->header('Content-Type', 'text/xml');
            }

            $data = [
                "Result" => [
                    '@attributes' => [
                        'Name' => "GetAccountDetails",
                        'Success' => $success
                    ],
                    'Returnset' => [
                        'Token' => $user->id,
                        'LoginName' => (string)$user->id,
                        'Currency' => "BRL",
                        'Country' => "BR",
                        'Birthdate' => "1998-01-01",
                        'Registration' => $user->created_at,
                        'Gender' => "m",
                    ]
                ]
            ];
        }

        if ($info["method"] == "GetBalance") {

            if ($user === null) {
                $data = [
                    "Result" => [
                        '@attributes' => [
                            'Name' => "GetAccountDetails",
                            'Success' => 0
                        ],
                        'Returnset' => [
                            'Error' => "Error retrieving Token",
                            'ErrorCode' => 1
                        ]
                    ]
                ];

                $responseXml = $this->arrayToXml($data);

                return response($responseXml, 200)->header('Content-Type', 'text/xml');
            }

            $wallet = $user->wallet()->first();
            $data = [
                "Result" => [
                    '@attributes' => [
                        'Name' => "GetBalance",
                        'Success' => $success
                    ],
                    'Returnset' => [
                        'Token' => $user->id,
                        'Balance' => $wallet->saldo * 100,
                        'Currency' => "BRL"
                    ]
                ]
            ];
        }

        if ($info["method"] == "AwardWinnings") {

            if ($user === null) {
                $data = [
                    "Result" => [
                        '@attributes' => [
                            'Name' => "AwardWinnings",
                            'Success' => $success
                        ],
                        'Returnset' => [
                            'error' => "Error retrieving Token",
                            'ErrorCode' => 1
                        ]
                    ]
                ];

                $responseXml = $this->arrayToXml($data);

                return response($responseXml, 200)->header('Content-Type', 'text/xml');
            }

            $wallet = $user->wallet()->first();
            $betInfo = $this->xmlToArray($request->getContent(), 'win');
            $action = $betInfo['transaction_id'] . '-' . $betInfo['win_reference'];
            $slot = SlotsTransaction::checkReplicated($action);
            if ($slot !== null) {
                $data = [
                    "Result" => [
                        '@attributes' => [
                            'Name' => "AwardWinnings",
                            'Success' => $success
                        ],
                        'Returnset' => [
                            'Token' => $user->id,
                            'Balance' => $wallet->saldo * 100,
                            'Currency' => "BRL",
                            "AlreadyProcessed" => true,
                            "ExtTransactionID" => $slot["id"]
                        ]
                    ]
                ];

                $responseXml = $this->arrayToXml($data);

                return response($responseXml, 200)->header('Content-Type', 'text/xml');
            }


            $amount = ($betInfo['amount'] / 100);
            $wallet->increment('balance', $amount);

            $slot = new SlotsTransaction();

            $slot->fill([
                'game' => $betInfo['GameReference'],
                'game_id' => $betInfo['GameReference'],
                'action' => 'win',
                'action_id' => $betInfo['transaction_id'] . '-' . $betInfo['win_reference'],
                'user_id' => $user->id,
                'provider' => 'salsa',
                'value' => $amount,
                'site_id' => $user->site_id
            ]);

            $slot->save();
            $gameHistory = GamesHistoric::where('user_id', $user->id)
                ->where('game_id', $betInfo['transaction_id'])
                ->where('created_at', '>', Carbon::now()
                    ->subMinutes(15)
                    ->format('Y-m-d H:i:s'))
                ->latest()
                ->first();

            if ($gameHistory !== null) {
                $gameHistory->value_win = $amount;
                $gameHistory->save();
            }

            if ($gameHistory === null) {
                $gameHistory = new GamesHistoric();
                $gameHistory->fill([
                    'user_id' => $user->id,
                    'cod' => Str::random(12),
                    'game_id' => $betInfo['GameReference'],
                    'action_id' => $betInfo['transaction_id'],
                    'value_bet' => 0,
                    'value_win' => $amount,
                    'site_id' => $user->site_id
                ]);
                $gameHistory->save();
            }

            $data = [
                "Result" => [
                    '@attributes' => [
                        'Name' => "GetBalance",
                        'Success' => $success
                    ],
                    'Returnset' => [
                        'Token' => $user->id,
                        'Balance' => $wallet->saldo * 100,
                        'Currency' => "BRL",
                        "AlreadyProcessed" => false,
                        "ExtTransactionID" => $slot->id
                    ]
                ]
            ];
        }

        if ($info["method"] == "ChangeGameToken") {
            if ($user === null) {
                $data = [
                    "Result" => [
                        '@attributes' => [
                            'Name' => "ChangeGameToken",
                            'Success' => $success
                        ],
                        'Returnset' => [
                            'error' => "Error retrieving Token",
                            'ErrorCode' => 1
                        ]
                    ]
                ];

                $responseXml = $this->arrayToXml($data);

                return response($responseXml, 200)->header('Content-Type', 'text/xml');
            }

            $data = [
                "Result" => [
                    '@attributes' => [
                        'Name' => "ChangeGameToken",
                        'Success' => $success
                    ],
                    'Returnset' => [
                        'NewToken' => $user->id
                    ]
                ]
            ];
        }
        if ($info["method"] == "RefundBet") {
            if ($user === null) {
                $data = [
                    "Result" => [
                        '@attributes' => [
                            'Name' => "AwardWinnings",
                            'Success' => $success
                        ],
                        'Returnset' => [
                            'error' => "Error retrieving Token",
                            'ErrorCode' => 1
                        ]
                    ]
                ];

                $responseXml = $this->arrayToXml($data);

                return response($responseXml, 200)->header('Content-Type', 'text/xml');
            }
            $wallet = $user->wallet()->first();
            $betInfo = $this->xmlToArray($request->getContent(), 'refund');
            $action = $betInfo['transaction_id'] . '-' . $betInfo['bet_reference'];
            $slot = SlotsTransaction::checkReplicated($action);

            if ($slot === null) {
                $data = [
                    "Result" => [
                        '@attributes' => [
                            'Name' => "RefundBet",
                            'Success' => 0
                        ],
                        'Returnset' => [
                            'Error' => "Transaction not found",
                            'ErrorCode' => 7,
                            'Balance' => $wallet->saldo * 100,
                            'Currency' => "BRL",
                        ]
                    ]
                ];

                $responseXml = $this->arrayToXml($data);

                return response($responseXml, 200)->header('Content-Type', 'text/xml');
            }

            $checkRefound = SlotsTransaction::where('action', 'refund')->where('action_id', $action)->first();

            if ($checkRefound !== null) {
                $data = [
                    "Result" => [
                        '@attributes' => [
                            'Name' => "RefundBet",
                            'Success' => $success
                        ],
                        'Returnset' => [
                            'Token' => $user->id,
                            'Balance' => $wallet->saldo * 100,
                            'Currency' => "BRL",
                            "AlreadyProcessed" => true,
                            "ExtTransactionID" => $checkRefound->id
                        ]
                    ]
                ];
            } else {
                $amount = ($betInfo['amount'] / 100);


                $wallet->increment('balance', $amount);

                $slot = new SlotsTransaction();

                $slot->fill([
                    'game' => $betInfo['GameReference'],
                    'game_id' => $betInfo['GameReference'],
                    'action' => 'refund',
                    'action_id' => $action,
                    'user_id' => $user->id,
                    'provider' => 'salsa',
                    'value' => $amount,
                    'site_id' => $user->site_id
                ]);

                $slot->save();

                $data = [
                    "Result" => [
                        '@attributes' => [
                            'Name' => "RefundBet",
                            'Success' => $success
                        ],
                        'Returnset' => [
                            'Token' => $user->id,
                            'Balance' => $wallet->saldo * 100,
                            'Currency' => "BRL",
                            "AlreadyProcessed" => false,
                            "ExtTransactionID" => $slot->id
                        ]
                    ]
                ];

            }
        }
        if ($info["method"] == "PlaceBet") {
            $betInfo = $this->xmlToArray($request->getContent(), 'bet');


            if ($user === null) {
                $data = [
                    "Result" => [
                        '@attributes' => [
                            'Name' => "AwardWinnings",
                            'Success' => $success
                        ],
                        'Returnset' => [
                            'Error' => "Error retrieving Token",
                            'ErrorCode' => 1
                        ]
                    ]
                ];

                $responseXml = $this->arrayToXml($data);

                return response($responseXml, 200)->header('Content-Type', 'text/xml');
            }

            $wallet = $user->wallet()->first();
            $action = $betInfo['transaction_id'] . '-' . $betInfo['bet_reference'];
            $slot = SlotsTransaction::checkReplicated($action);
            if ($slot !== null) {
                $data = [
                    "Result" => [
                        '@attributes' => [
                            'Name' => "PlaceBet",
                            'Success' => $success
                        ],
                        'Returnset' => [
                            'Token' => $user->id,
                            'Balance' => $wallet->saldo * 100,
                            'Currency' => "BRL",
                            "AlreadyProcessed" => true,
                            "ExtTransactionID" => $slot["id"]
                        ]
                    ]
                ];
            } else {
                $amount = ($betInfo['amount'] / 100);

                if ($wallet->saldo < $amount) {
                    $data = [
                        "Result" => [
                            '@attributes' => [
                                'Name' => "PlaceBet",
                                'Success' => 0
                            ],
                            'Returnset' => [
                                'Error' => "Insufficient funds",
                                'ErrorCode' => 6,
                                'Balance' => $wallet->saldo * 100,
                                'Currency' => "BRL",
                            ]
                        ]
                    ];

                    $responseXml = $this->arrayToXml($data);

                    return response($responseXml, 200)->header('Content-Type', 'text/xml');
                }

                $wallet->decrement('balance', $amount);

                $slot = new SlotsTransaction();
                $slot->fill([
                    'game' => $betInfo['bet_reference'],
                    'game_id' => $betInfo['GameReference'],
                    'action' => 'bet',
                    'action_id' => $betInfo['transaction_id'],
                    'user_id' => $user->id,
                    'provider' => 'salsa',
                    'value' => $amount,
                    'site_id' => $user->site_id
                ]);

                $slot->save();


                $gameHistory = new GamesHistoric();
                $gameHistory->fill([
                    'user_id' => $user->id,
                    'cod' => Str::random(12),
                    'game_id' => $betInfo['GameReference'],
                    'action_id' => $betInfo['transaction_id'],
                    'value_bet' => $amount,
                    'value_win' => 0,
                    'site_id' => $user->site_id
                ]);
                $gameHistory->save();
                $data = [
                    "Result" => [
                        '@attributes' => [
                            'Name' => "GetBalance",
                            'Success' => $success
                        ],
                        'Returnset' => [
                            'Token' => $user->id,
                            'Balance' => $wallet->saldo * 100,
                            'Currency' => "BRL",
                            "AlreadyProcessed" => false,
                            "ExtTransactionID" => $slot->id
                        ]
                    ]
                ];
            }
        }
        $responseXml = $this->arrayToXml($data);

        $user->save();
        return response($responseXml, 200)->header('Content-Type', 'text/xml');

    }

    public function xmlToArray($xmlString, $betInfo = "")
    {
        $xml = simplexml_load_string($xmlString);

        if ($betInfo === "bet") {
            return [
                "transaction_id" => $xml->Method->Params->TransactionID["Value"],
                "bet_reference" => $xml->Method->Params->BetReferenceNum["Value"],
                "amount" => $xml->Method->Params->BetAmount["Value"],
                "GameReference" => $xml->Method->Params->GameReference["Value"],
            ];
        }
        if ($betInfo === "win") {
            return [
                "transaction_id" => $xml->Method->Params->TransactionID["Value"],
                "win_reference" => $xml->Method->Params->WinReferenceNum["Value"],
                "amount" => $xml->Method->Params->WinAmount["Value"],
                "GameReference" => $xml->Method->Params->GameReference["Value"],
            ];
        }
        if ($betInfo === "refund") {
            return [
                "transaction_id" => $xml->Method->Params->TransactionID["Value"],
                "amount" => $xml->Method->Params->RefundAmount["Value"],
                "bet_reference" => $xml->Method->Params->BetReferenceNum["Value"],
                "GameReference" => $xml->Method->Params->GameReference["Value"],
            ];
        }

        $method = (string)$xml->Method['Name'];
        $token = (string)$xml->Method->Params->Token['Value'];

        $array = [
            'method' => $method,
            'token' => $token
        ];
        return $array;
    }

    public function checkToken($xmlString, $token, $hashCheck)
    {
        $xml = simplexml_load_string($xmlString);

        $params = $xml->Method->Params;
        $paramsValues = [];
        foreach ($params->children() as $param) {
            $paramsValues[] = (string)$param['Value'];
        }
        $paramsValues[] = $token;
        $dataToHash = implode('', $paramsValues);

        $hash = hash('sha256', $dataToHash);
        return $hash == $hashCheck;
    }

    public function arrayToXml($array, $rootElementName = 'PKT')
    {
        $xml = new SimpleXMLElement('<' . $rootElementName . '></' . $rootElementName . '>');
        $this->arrayToXmlHelper($array, $xml);
        return $xml->asXML();
    }

    public function arrayToXmlHelper($array, &$xml)
    {
        foreach ($array as $key => $value) {
            if ($key === '@attributes') {
                foreach ($value as $attrKey => $attrValue) {
                    $xml->addAttribute($attrKey, $attrValue);
                }
            } elseif (is_array($value)) {
                $subnode = $xml->addChild($key);
                $this->arrayToXmlHelper($value, $subnode);
            } else {
                $child = $xml->addChild($key);
                if ($key === "Balance") {
                    $child->addAttribute('Type', 'int');

                    $child->addAttribute('Value', $value);
                } elseif ($key === "AlreadyProcessed") {
                    $child->addAttribute('Type', 'bool');

                    $child->addAttribute('Value', substr($value, 0, 10));
                } elseif ($key === "ExtTransactionID") {
                    $child->addAttribute('Type', 'long');

                    $child->addAttribute('Value', substr($value, 0, 10));
                } elseif ($key === 'Birthdate' || $key === 'Registration') {
                    $child->addAttribute('Type', 'date');

                    $child->addAttribute('Value', substr($value, 0, 10));
                } else {
                    $child->addAttribute('Type', 'string');
                    $child->addAttribute('Value', $value);
                }

            }
        }
    }
}
