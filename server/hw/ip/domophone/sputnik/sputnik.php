<?php

namespace hw\ip\domophone\sputnik;

use hw\ip\domophone\domophone;

/**
 * Class representing a Sputnik domophone.
 */
class sputnik extends domophone
{

    use \hw\ip\common\sputnik\sputnik;

    protected array $rfidKeysToBeDeleted = [];

    /**
     * @var array|null $flats An array that holds flats information,
     * which may be null if not loaded.
     */
    protected ?array $flats = null;

    /**
     * @var array|null $personalCodes An array that holds personal access codes information,
     * which may be null if not loaded.
     */
    protected ?array $personalCodes = null;

    protected array $cmsModelType = [
        'BK-100' => 'VIZIT',
        'COM-25U' => 'METACOM',
        'COM-100U' => 'METACOM',
        'COM-220U' => 'METACOM',
        'KM100-7.1' => 'ELTIS',
        'KM100-7.2' => 'ELTIS',
        'KM100-7.3' => 'ELTIS',
        'KM100-7.5' => 'ELTIS',
        'KMG-100' => 'CYFRAL',
    ];

    public function addRfid(string $code, int $apartment = 0)
    {
        $this->apiCall('mutation', 'addIntercomKey', [
            'intercomID' => $this->uuid,
            'key' => substr(implode(array_reverse(str_split($code, 2))), 0, 8), // invert and remove zeros
            'description' => '',
        ]);
    }

    public function addRfids(array $rfids)
    {
        $keys = [];

        foreach ($rfids as $rfid) {
            $keys[] = [
                'Description' => '',
                'Key' => substr(implode(array_reverse(str_split($rfid, 2))), 0, 8), // invert and remove zeros
            ];
        }

        $this->apiCall('mutation', 'addIntercomKeys', [
            'intercomID' => $this->uuid,
            'keys' => $keys,
        ]);
    }

    public function configureApartment(
        int   $apartment,
        int   $code = 0,
        array $sipNumbers = [],
        bool  $cmsEnabled = true,
        array $cmsLevels = []
    )
    {
        $this->loadFlats();
        $this->loadPersonalCodes();

        $flat = &$this->flats[$apartment];

        $flat['num'] = $apartment;
        $flat['sipAccountContact'] = "$sipNumbers[0]" ?? null;
        $flat['analogSettings']['alias'] = $flat['analogSettings']['alias'] ?? 0;
        $flat['analogSettings']['blocked'] = !$cmsEnabled;
        $flat['analogSettings']['thresholdCall'] = $cmsLevels[0] ?? 9.99;
        $flat['analogSettings']['thresholdDoor'] = $cmsLevels[1] ?? 9.99;

        if ($code !== 0) {
            $this->personalCodes[$apartment] = $code;
        }
    }

    public function configureEncoding()
    {
        // Empty implementation
    }

    public function configureGate(array $links = [])
    {
//        $this->apiCall('mutation', 'removeAllClusterPrefix', ['uuid' => $this->uuid]);
//
//        $clusterPrefixes = array_map(function ($link) {
//            return [
//                'prefix' => $link['prefix'],
//                'firstFlat' => $link['firstFlat'],
//                'lastFlat' => $link['lastFlat'],
//                'voiceText' => $link['address']
//            ];
//        }, $links);
//
//        $this->apiCall('mutation', 'addClusterPrefixesToIntercom', [
//            'intercomID' => $this->uuid,
//            'clusterPrefixes' => $clusterPrefixes,
//        ]);
    }

    public function configureMatrix(array $matrix)
    {
        $this->loadFlats();

        // Clear all aliases (analog numbers)
        foreach ($this->flats as &$flat) {
            $flat['analogSettings']['alias'] = 0;
        }

        // Configure the necessary aliases
        foreach ($matrix as $cell) {
            [
                'hundreds' => $hundreds,
                'tens' => $tens,
                'units' => $units,
                'apartment' => $apartment
            ] = $cell;

            $this->flats[$apartment]['analogSettings']['alias'] = intval($hundreds . $tens . $units);
        }
    }

    public function configureSip(
        string $login,
        string $password,
        string $server,
        int    $port = 5060,
        bool   $stunEnabled = false,
        string $stunServer = '',
        int    $stunPort = 3478
    )
    {
        $this->apiCall('mutation', 'updateIntercomSipParameters', [
            'intercomID' => $this->uuid,
            'sipParameters' => [
                'incomingCall' => true,
                'login' => $login,
                'password' => $password,
                'permanentSipConnection' => true,
                'server' => "$server:$port",
                'username' => $login,
            ]
        ]);
    }

    public function configureUserAccount(string $password)
    {
        // Empty implementation
    }

    public function deleteApartment(int $apartment = 0)
    {
        $this->loadFlats();
        $this->loadPersonalCodes();

        if ($apartment !== 0) {
            $this->flats[$apartment]['sipAccountContact'] = null;
            unset($this->personalCodes[$apartment]);
        } else {
            // TODO: deleting all apartments
        }
    }

    public function deleteRfid(string $code = '')
    {
        $this->rfidKeysToBeDeleted[] = substr(implode(array_reverse(str_split($code, 2))), 0, 8);
    }

    public function getLineDiagnostics(int $apartment)
    {
        $lineData = $this->apiCall('mutation', 'lineTest', [
            'intercomID' => $this->uuid,
            'flatNum' => $apartment,
        ], ['data']);

        return $lineData['data']['lineTest']['data']['com_line_voltage'];
    }

    public function openLock(int $lockNumber = 0)
    {
        $this->apiCall('mutation', $lockNumber ? 'openSecondDoor' : 'openDoor', ['intercomID' => $this->uuid]);
    }

    public function setAudioLevels(array $levels)
    {
        if (count($levels) === 4) {
            $this->apiCall('mutation', 'updateIntercomSoundConfig', [
                'intercomID' => $this->uuid,
                'general' => $levels[0],
                'speakHandsetTx' => $levels[1],
                'speakLoudspeaker' => $levels[2],
                'speakSIP' => $levels[3],
            ]);
        }
    }

    public function setCallTimeout(int $timeout)
    {
        $this->apiCall('mutation', 'updateIntercomCallConfig', [
            'intercomID' => $this->uuid,
            'flatDialingTimeTimeout' => $timeout * 1000,
        ]);
    }

    public function setCmsLevels(array $levels)
    {
        $this->apiCall('mutation', 'updateIntercomFlatConfig', [
            'intercomID' => $this->uuid,
            'defaultThresholdCall' => (float)$levels[0],
            'defaultThresholdDoor' => (float)$levels[1],
        ]);
    }

    public function setCmsModel(string $model = '')
    {
        $this->apiCall('mutation', 'updateIntercomCommutatorConfig', [
            'intercomID' => $this->uuid,
            'commutatorType' => "ENUM::{$this->cmsModelType[$model]}",
        ]);
    }

    public function setConciergeNumber(int $sipNumber)
    {
        // Empty implementation
        // $this->configureApartment($sipNumber, 0, [$sipNumber], false);
    }

    public function setDtmfCodes(string $code1 = '1', string $code2 = '2', string $code3 = '3', string $codeCms = '1')
    {
        $this->apiCall('mutation', 'updateIntercomSipParameters', [
            'intercomID' => $this->uuid,
            'sipParameters' => ['dtmfOpenDoor' => $code1],
        ]);
    }

    public function setLanguage(string $language = 'ru')
    {
        // Empty implementation
    }

    public function setPublicCode(int $code = 0)
    {
        // Empty implementation
    }

    public function setSosNumber(int $sipNumber)
    {
        $this->apiCall('mutation', 'updateIntercomOptionalButtonParameters', [
            'intercomID' => $this->uuid,
            'optionalButton' => [
                'sipCallUri' => "$sipNumber",
                'useMainSipCreds' => true,
            ],
        ]);
    }

    public function setTalkTimeout(int $timeout)
    {
        $this->apiCall('mutation', 'updateIntercomCallConfig', [
            'intercomID' => $this->uuid,
            'flatCallTimeTimeout' => $timeout * 1000,
        ]);
    }

    public function setTickerText(string $text = '')
    {
        // Empty implementation
    }

    public function setUnlockTime(int $time = 3)
    {
        $this->apiCall('mutation', 'updateIntercomOpenDoorConfig', [
            'intercomID' => $this->uuid,
            'general' => $time,
            'social' => $time,
            'bluetooth' => $time,
        ]);
    }

    public function setUnlocked(bool $unlocked = true)
    {
        // Empty implementation
    }

    public function syncData()
    {
        $this->uploadFlats();
        // $this->uploadPersonalCodes(); // TODO: implement

        if ($this->rfidKeysToBeDeleted) {
            $this->deleteIntercomKeys($this->rfidKeysToBeDeleted);
        }
    }

    public function transformDbConfig(array $dbConfig): array
    {
        $dbConfig['tickerText'] = '';
        $dbConfig['unlocked'] = false;
        $dbConfig['cmsModel'] = $this->cmsModelType[$dbConfig['cmsModel']];

        $dbConfig['sip']['stunServer'] = '';
        $dbConfig['sip']['stunPort'] = 3478;

        $dbConfig['ntp']['server'] = '';
        $dbConfig['ntp']['port'] = 123;
        $dbConfig['ntp']['timezone'] = $this->getOffsetByTimezone($dbConfig['ntp']['timezone']);

        return $dbConfig;
    }

    protected function deleteIntercomKeys($keys)
    {
        $this->apiCall('mutation', 'deleteIntercomKeys', [
            'intercomID' => $this->uuid,
            'keys' => $keys,
        ]);
    }

    protected function getApartments(): array
    {
        $flats = [];
        $codes = [];

        $intercom = $this->apiCall('query', 'intercom', ['uuid' => $this->uuid], [
            'configShadow' => [
                'flats' => [
                    'flats(limit: 9999)' => [
                        'edges' => [
                            'node' => [
                                'num',
                                'sipAccountContact',
                                'analogSettings' => [
                                    'blocked',
                                    'thresholdCall',
                                    'thresholdDoor',
                                ],
                            ],
                        ],
                    ],
                ],
                'keys' => [
                    'digitalKeys' => [
                        'edges' => [
                            'description',
                            'node' => [
                                'uuid',
                                'value',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $rawFlats = $intercom['data']['intercom']['configShadow']['flats']['flats']['edges'];
        $rawCodes = $intercom['data']['intercom']['configShadow']['keys']['digitalKeys']['edges'];

        foreach ($rawCodes as $rawCode) {
            $code = $rawCode['node']['value'];
            $description = $rawCode['description'];
            $codes[intval($description)] = $code;
        }

        foreach ($rawFlats as $rawFlat) {
            [
                'num' => $apartment,
                'sipAccountContact' => $sipNumber,
                'analogSettings' => $analogSettings,
            ] = $rawFlat['node'];

            // Generated automatically due to range, not required
            if (!$sipNumber) {
                continue;
            }

            [
                'blocked' => $cmsBlocked,
                'thresholdCall' => $thresholdCall,
                'thresholdDoor' => $thresholdDoor,
            ] = $analogSettings;

            $flats[$apartment] = [
                'apartment' => $apartment,
                'code' => $codes[$apartment] ?? 0,
                'sipNumbers' => [$sipNumber],
                'cmsEnabled' => !$cmsBlocked,
                'cmsLevels' => [$thresholdCall, $thresholdDoor]
            ];
        }

        return $flats;
    }

    protected function getAudioLevels(): array
    {
        $rawAudioLevels = $this->apiCall('query', 'intercom', ['uuid' => $this->uuid], [
            'configShadow' => ['soundVolumes' => ['general', 'speakHandsetTx', 'speakLoudspeaker ', 'speakSIP']],
        ]);

        [
            'general' => $general,
            'speakHandsetTx' => $speakHandsetTx,
            'speakLoudspeaker' => $speakLoudspeaker,
            'speakSIP' => $speakSIP,
        ] = $rawAudioLevels['data']['intercom']['configShadow']['soundVolumes'];

        return [$general, $speakHandsetTx, $speakLoudspeaker, $speakSIP];
    }

    protected function getCmsLevels(): array
    {
        $rawCmsLevels = $this->apiCall('query', 'intercom', ['uuid' => $this->uuid], [
            'configShadow' => ['flats' => ['defaultThresholdCall', 'defaultThresholdDoor']]
        ]);

        [
            'defaultThresholdCall' => $thresholdCall,
            'defaultThresholdDoor' => $thresholdDoor,
        ] = $rawCmsLevels['data']['intercom']['configShadow']['flats'];

        return [$thresholdCall, $thresholdDoor];
    }

    protected function getCmsModel(): string
    {
        $intercom = $this->apiCall('query', 'intercom', ['uuid' => $this->uuid], [
            'configShadow' => ['commutator' => ['commutatorType']]
        ]);

        return $intercom['data']['intercom']['configShadow']['commutator']['commutatorType'];
    }

    protected function getDtmfConfig(): array
    {
        $intercom = $this->apiCall('query', 'intercom', ['uuid' => $this->uuid], [
            'configShadow' => ['calls' => ['sipAccount' => ['dtmfOpenDoor']]]
        ]);

        $dtmfCode = $intercom['data']['intercom']['configShadow']['calls']['sipAccount']['dtmfOpenDoor'] ?? '';

        return [
            'code1' => $dtmfCode,
            'code2' => '2',
            'code3' => '3',
            'codeCms' => '1',
        ];
    }

    protected function getGateConfig(): array
    {
        return [];
    }

    protected function getMatrix(): array
    {
        $matrix = [];

        $intercom = $this->apiCall('query', 'intercom', ['uuid' => $this->uuid], [
            'configShadow' => [
                'flats' => [
                    'flats(limit: 9999)' => [
                        'edges' => [
                            'node' => [
                                'num',
                                'analogSettings' => ['alias'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $rawMatrix = $intercom['data']['intercom']['configShadow']['flats']['flats']['edges'];

        foreach ($rawMatrix as $cell) {
            $alias = $cell['node']['analogSettings']['alias'];

            // No analog line forwarding, skip
            if (!$alias) {
                continue;
            }

            $apartmentNumber = $cell['node']['num'];
            [$cms, $dozen, $unit] = str_split(str_pad($alias, 3, '0', STR_PAD_LEFT));

            $matrix[$cms . $dozen . $unit] = [
                'hundreds' => $cms,
                'tens' => $dozen,
                'units' => $unit,
                'apartment' => $apartmentNumber,
            ];
        }

        return $matrix;
    }

    protected function getRfids(): array
    {
        $intercom = $this->apiCall('query', 'intercom', ['uuid' => $this->uuid], [
            'configShadow' => ['keys' => ['keys' => ['edges' => ['node']]]],
        ]);

        $rawKeys = $intercom['data']['intercom']['configShadow']['keys']['keys']['edges'];

        $keys = array_map(
            fn($code) => strtoupper(str_pad(implode(array_reverse(str_split($code, 2))), 14, '0', STR_PAD_LEFT)),
            array_column($rawKeys, 'node')
        );

        return array_combine($keys, $keys);
    }

    protected function getSipConfig(): array
    {
        $intercom = $this->apiCall('query', 'intercom', ['uuid' => $this->uuid], [
            'configShadow' => ['calls' => ['sipAccount' => ['login', 'password', 'server', 'username']]]
        ]);

        $rawSipConfig = $intercom['data']['intercom']['configShadow']['calls']['sipAccount'];
        $login = $rawSipConfig['login'] ?? '';
        $password = $rawSipConfig['password'] ?? '';
        $url = $rawSipConfig['server'] ?? '';

        [$server, $port] = array_pad(explode(':', $url), 2, 5060);

        return [
            'server' => $server,
            'port' => $port,
            'login' => $login,
            'password' => $password,
            'stunEnabled' => false,
            'stunServer' => '',
            'stunPort' => 3478,
        ];
    }

    protected function getTickerText(): string
    {
        return '';
    }

    protected function getUnlocked(): bool
    {
        return false;
    }

    /**
     * Load and cache flats from the API if they haven't been loaded already.
     *
     * @return void
     */
    protected function loadFlats()
    {
        if ($this->flats !== null) {
            return;
        }

        $intercom = $this->apiCall('query', 'intercom', ['uuid' => $this->uuid], [
            'configShadow' => [
                'flats' => [
                    'firstFlat',
                    'lastFlat',
                    'flats(limit: 9999)' => [
                        'edges' => [
                            'node' => [
                                'num',                  // Flat number
                                'sipAccountContact',    // SIP number
                                'analogSettings' => [
                                    'alias',            // Analog number
                                    'blocked',          // CMS blocked
                                    'thresholdCall',    // Handset up level
                                    'thresholdDoor',    // Door opening level
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        $rawFlats = $intercom['data']['intercom']['configShadow']['flats']['flats']['edges'];

        $flats = array_map(function ($item) {
            return $item['node'];
        }, $rawFlats);

        $this->flats = array_column($flats, null, 'num');
    }

    /**
     * Load and cache personal codes from the API if they haven't been loaded already.
     *
     * @return void
     */
    protected function loadPersonalCodes()
    {
        if ($this->personalCodes !== null) {
            return;
        }

        $intercom = $this->apiCall('query', 'intercom', ['uuid' => $this->uuid], [
            'configShadow' => [
                'keys' => [
                    'digitalKeys' => [
                        'edges' => [
                            'description',
                            'node' => [
                                'uuid',
                                'value'
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        $rawCodes = $intercom['data']['intercom']['configShadow']['keys']['digitalKeys']['edges'];

        $this->personalCodes = array_reduce($rawCodes, function ($result, $item) {
            $description = $item['description'];

            $result[$description] = [
                'uuid' => $item['node']['uuid'],
                'value' => $item['node']['value'],
            ];

            return $result;
        }, []);
    }

    protected function uploadFlats()
    {
        if ($this->flats === null) {
            return;
        }

        // Filter all empty flats without a SIP number and without an analog number
        $filteredFlats = array_filter($this->flats, function ($flat) {
            return !empty($flat['sipAccountContact']) || !empty($flat['analogSettings']['alias']);
        });

        $flatNumbers = array_keys($filteredFlats);
        $firstFlat = min($flatNumbers);
        $lastFlat = max($flatNumbers);

        // Upload flat range
        $this->apiCall('mutation', 'updateIntercomFlatConfig', [
            'intercomID' => $this->uuid,
            'firstFlat' => $firstFlat,
            'lastFlat' => $lastFlat,
        ]);

        sleep(5); // Without this, with a large range of flats, the last flat is duplicated

        $flats = array_map(function ($flat) {
            return [
                'flatNum' => $flat['num'],
                'parameters' => [
                    'blocked' => false,
                    'redirection' => true,
                    'sipAccountContact' => $flat['sipAccountContact'],
                    'soundVol' => 100,
                    'analogSettings' => [
                        'alias' => $flat['analogSettings']['alias'],
                        'blocked' => $flat['analogSettings']['blocked'],
                        'thresholdCall' => $flat['analogSettings']['thresholdCall'],
                        'thresholdDoor' => $flat['analogSettings']['thresholdDoor'],
                    ],
                ],
            ];
        }, array_values($filteredFlats));

        // Upload flats
        $this->apiCall('mutation', 'updateIntercomFlats', [
            'intercomID' => $this->uuid,
            'flats' => $flats
        ]);
    }

    protected function uploadPersonalCodes()
    {
        if ($this->personalCodes !== null) {
            $this->apiCall('mutation', 'createDigitalKeys', [
                'intercomID' => $this->uuid,
                'digitalKeys' => $this->personalCodes,
            ]);
        }
    }
}
