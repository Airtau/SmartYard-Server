<?php

    /**
     * backends billing namespace
     */

    namespace backends\billing {

        /**
         * internal billing class
         */

        class internal extends billing {

            /**
             * @inheritDoc
             */

            function setContractsBindings($items) {
                if (!is_array($items)) {
                    setLastError("invalidParams");
                    return false;
                }

                $households = loadBackend("households");

                if (!$households) {
                    return false;
                }

                $result = [
                    "processed" => 0,
                    "updated" => 0,
                    "invalid" => 0,
                    "notFound" => 0,
                    "failed" => 0,
                    "errors" => [],
                ];

                $houseFlats = [];

                foreach ($items as $index => $item) {
                    $result["processed"]++;

                    if (!is_array($item)) {
                        $result["invalid"]++;
                        $result["errors"][] = [
                            "index" => $index,
                            "error" => "invalidItem",
                        ];
                        continue;
                    }

                    $houseId = @$item["houseId"];
                    $flat = @$item["flat"];
                    $contract = @$item["contract"];

                    if (!checkInt($houseId) || !$houseId || !checkStr($flat) || !$flat || !checkStr($contract) || !$contract) {
                        $result["invalid"]++;
                        $result["errors"][] = [
                            "index" => $index,
                            "error" => "invalidParams",
                            "houseId" => @$item["houseId"],
                            "flat" => @$item["flat"],
                            "address" => @$item["address"],
                            "contract" => @$item["contract"],
                        ];
                        continue;
                    }

                    if (!array_key_exists($houseId, $houseFlats)) {
                        $houseFlats[$houseId] = $households->getFlats("houseId", $houseId);

                        if (!is_array($houseFlats[$houseId])) {
                            $houseFlats[$houseId] = [];
                        }
                    }

                    $flatIds = [];

                    foreach ($houseFlats[$houseId] as $houseFlat) {
                        $_flat = @$houseFlat["flat"];
                        $_flatId = @$houseFlat["flatId"];

                        if (!checkStr($_flat) || !checkInt($_flatId)) {
                            continue;
                        }

                        if ($_flat === $flat) {
                            $flatIds[] = $_flatId;
                        }
                    }

                    if (!count($flatIds)) {
                        $result["notFound"]++;
                        $result["errors"][] = [
                            "index" => $index,
                            "error" => "flatNotFound",
                            "houseId" => $houseId,
                            "flat" => $flat,
                            "address" => @$item["address"],
                            "contract" => $contract,
                        ];
                        continue;
                    }

                    foreach ($flatIds as $flatId) {
                        $params = [
                            "contract" => $contract,
                        ];

                        if (array_key_exists("autoBlock", $item)) {
                            $params["autoBlock"] = $item["autoBlock"];
                        }

                        if (array_key_exists("login", $item) || array_key_exists("password", $item)) {
                            $currentFlat = $households->getFlat($flatId);

                            if (!$currentFlat) {
                                $result["failed"]++;
                                $result["errors"][] = [
                                    "index" => $index,
                                    "error" => "cantGetFlat",
                                    "flatId" => $flatId,
                                    "houseId" => $houseId,
                                    "flat" => $flat,
                                    "address" => @$item["address"],
                                    "contract" => $contract,
                                ];
                                continue;
                            }

                            $params["login"] = array_key_exists("login", $item) ? $item["login"] : @$currentFlat["login"];
                            $params["password"] = array_key_exists("password", $item) ? $item["password"] : @$currentFlat["password"];
                        }

                        if ($households->modifyFlat($flatId, $params) === false) {
                            $result["failed"]++;
                            $result["errors"][] = [
                                "index" => $index,
                                "error" => "cantModifyFlat",
                                "flatId" => $flatId,
                                "houseId" => $houseId,
                                "flat" => $flat,
                                "address" => @$item["address"],
                                "contract" => $contract,
                            ];
                            continue;
                        }

                        $result["updated"]++;
                    }
                }

                return $result;
            }

            /**
             * @inheritDoc
             */

            function syncAutoBlockByContracts($subscribers, $defaultAction = "skipMissing") {
                if (!is_array($subscribers) || !is_string($defaultAction) || !in_array($defaultAction, [ "skipMissing", "blockMissing", "unblockMissing" ], true)) {
                    setLastError("invalidParams");
                    return false;
                }

                $households = loadBackend("households");
                $addresses = loadBackend("addresses");
                $customFields = loadBackend("customFields");

                if (!$households || !$addresses || !$customFields) {
                    return false;
                }

                $result = [
                    "processed" => 0,
                    "updated" => 0,
                    "invalid" => 0,
                    "notFound" => 0,
                    "failed" => 0,
                    "defaultAction" => $defaultAction,
                    "missing" => [
                        "updated" => 0,
                        "unchanged" => 0,
                        "failed" => 0,
                    ],
                    "errors" => [],
                ];

                $normalizedSubscribers = [];
                $pairs = [];
                $contracts = [];

                foreach ($subscribers as $index => $subscriber) {
                    $result["processed"]++;

                    if (!is_array($subscriber) ||
                        !array_key_exists("subscriberID", $subscriber) ||
                        !array_key_exists("agreement", $subscriber) ||
                        !array_key_exists("isActive", $subscriber) ||
                        !array_key_exists("addressText", $subscriber) ||
                        !array_key_exists("buildingUUID", $subscriber) ||
                        !array_key_exists("flatNumber", $subscriber)
                    ) {
                        $result["invalid"]++;
                        $result["errors"][] = [
                            "index" => $index,
                            "error" => "invalidItem",
                        ];
                        continue;
                    }

                    $subscriberID = $subscriber["subscriberID"];
                    $agreement = $subscriber["agreement"];
                    $isActive = $subscriber["isActive"];
                    $addressText = $subscriber["addressText"];
                    $buildingUUID = $subscriber["buildingUUID"];
                    $flatNumber = $subscriber["flatNumber"];

                    if (!checkInt($subscriberID) || !$subscriberID ||
                        !checkStr($agreement) || $agreement === "" ||
                        !checkInt($isActive) || !in_array((int)$isActive, [ 0, 1 ], true) ||
                        !checkStr($addressText) || $addressText === "" ||
                        !checkStr($buildingUUID) || $buildingUUID === "" ||
                        !checkStr($flatNumber) || $flatNumber === ""
                    ) {
                        $result["invalid"]++;
                        $result["errors"][] = [
                            "index" => $index,
                            "error" => "invalidParams",
                            "subscriberID" => $subscriber["subscriberID"],
                            "buildingUUID" => $subscriber["buildingUUID"],
                            "flatNumber" => $subscriber["flatNumber"],
                        ];
                        continue;
                    }

                    $contract = (string)$subscriberID;
                    $pairKey = $buildingUUID . "\n" . $flatNumber;

                    $normalizedSubscribers[] = [
                        "index" => $index,
                        "subscriberID" => $subscriberID,
                        "contract" => $contract,
                        "agreement" => $agreement,
                        "isActive" => (int)$isActive,
                        "addressText" => $addressText,
                        "buildingUUID" => $buildingUUID,
                        "flatNumber" => $flatNumber,
                        "pairKey" => $pairKey,
                    ];

                    $contracts[$contract] = $contract;
                    $pairs[$pairKey] = [
                        "buildingUUID" => $buildingUUID,
                        "flatNumber" => $flatNumber,
                    ];
                }

                if (count($normalizedSubscribers)) {
                    $pairRows = $households->getFlats("houseUuidFlat", array_values($pairs));

                    if (!is_array($pairRows)) {
                        $result["failed"]++;
                        $result["errors"][] = [
                            "error" => "cantGetFlatsByHouseUuidFlat",
                        ];
                        $pairRows = [];
                    }

                    $houseUuids = [];
                    $flatsByPair = [];

                    foreach ($pairRows as $row) {
                        $flatId = @$row["flatId"];
                        $houseId = @$row["houseId"];
                        $flat = @$row["flat"];

                        if (!checkInt($flatId) || !checkInt($houseId) || !checkStr($flat) || $flat === "") {
                            $result["failed"]++;
                            $result["errors"][] = [
                                "error" => "invalidRow",
                                "flatId" => @$row["flatId"],
                                "houseId" => @$row["houseId"],
                            ];
                            continue;
                        }

                        if (!array_key_exists($houseId, $houseUuids)) {
                            $house = $addresses->getHouse($houseId);
                            $houseUuid = @$house["houseUuid"];

                            if (!checkStr($houseUuid) || $houseUuid === "") {
                                $houseUuids[$houseId] = false;
                            } else {
                                $houseUuids[$houseId] = $houseUuid;
                            }
                        }

                        if (!$houseUuids[$houseId]) {
                            $result["failed"]++;
                            $result["errors"][] = [
                                "error" => "cantGetHouseUuid",
                                "flatId" => $flatId,
                                "houseId" => $houseId,
                            ];
                            continue;
                        }

                        $pairKey = $houseUuids[$houseId] . "\n" . $flat;

                        if (!array_key_exists($pairKey, $flatsByPair)) {
                            $flatsByPair[$pairKey] = [];
                        }

                        $flatsByPair[$pairKey][$flatId] = $row;
                    }

                    foreach ($normalizedSubscribers as $subscriber) {
                        $pairKey = $subscriber["pairKey"];
                        $pairFlats = @$flatsByPair[$pairKey];

                        if (!is_array($pairFlats) || !count($pairFlats)) {
                            $result["notFound"]++;
                            $result["errors"][] = [
                                "index" => $subscriber["index"],
                                "error" => "flatNotFound",
                                "subscriberID" => $subscriber["subscriberID"],
                                "buildingUUID" => $subscriber["buildingUUID"],
                                "flatNumber" => $subscriber["flatNumber"],
                            ];
                            continue;
                        }

                        foreach ($pairFlats as $flat) {
                            $flatId = @$flat["flatId"];

                            if (!checkInt($flatId)) {
                                $result["failed"]++;
                                $result["errors"][] = [
                                    "index" => $subscriber["index"],
                                    "error" => "invalidFlatId",
                                    "subscriberID" => $subscriber["subscriberID"],
                                ];
                                continue;
                            }

                            $autoBlock = $subscriber["isActive"] ? 0 : 1;

                            if ($households->modifyFlat($flatId, [
                                    "contract" => $subscriber["contract"],
                                    "autoBlock" => $autoBlock,
                                ]) === false) {
                                $result["failed"]++;
                                $result["errors"][] = [
                                    "index" => $subscriber["index"],
                                    "error" => "cantModifyFlat",
                                    "flatId" => $flatId,
                                    "subscriberID" => $subscriber["subscriberID"],
                                ];
                                continue;
                            }

                            $values = $customFields->getValues("flat", $flatId);

                            if (!is_array($values)) {
                                $values = [];
                            }

                            $values["agreement"] = $subscriber["agreement"];
                            $values["addressText"] = $subscriber["addressText"];

                            if ($customFields->modifyValues("flat", $flatId, $values) === false) {
                                $result["failed"]++;
                                $result["errors"][] = [
                                    "index" => $subscriber["index"],
                                    "error" => "cantModifyCustomFields",
                                    "flatId" => $flatId,
                                    "subscriberID" => $subscriber["subscriberID"],
                                ];
                                continue;
                            }

                            $result["updated"]++;
                        }
                    }
                }

                if ($defaultAction === "blockMissing" || $defaultAction === "unblockMissing") {
                    $missingRows = $households->getFlats("notContracts", array_values($contracts));

                    if (!is_array($missingRows)) {
                        $result["missing"]["failed"]++;
                        $result["errors"][] = [
                            "scope" => "missing",
                            "error" => "cantGetFlatsByNotContracts",
                        ];
                        $missingRows = [];
                    }

                    $missingTargetAutoBlock = ($defaultAction === "blockMissing") ? 1 : 0;
                    $this->syncAutoBlockRows($households, $missingRows, $missingTargetAutoBlock, "missing", $result);
                }

                return $result;
            }

            private function syncAutoBlockRows($households, $rows, $targetAutoBlock, $scope, &$result) {
                foreach ($rows as $row) {
                    $flatId = @$row["flatId"];
                    $currentAutoBlock = @$row["autoBlock"];
                    $contract = @$row["contract"];

                    if (!checkInt($flatId) || !checkInt($currentAutoBlock)) {
                        $result[$scope]["failed"]++;
                        $result["errors"][] = [
                            "scope" => $scope,
                            "error" => "invalidRow",
                            "flatId" => @$row["flatId"],
                            "contract" => $contract,
                        ];
                        continue;
                    }

                    if ($currentAutoBlock === (int)$targetAutoBlock) {
                        $result[$scope]["unchanged"]++;
                        continue;
                    }

                    if ($households->modifyFlat($flatId, [ "autoBlock" => (int)$targetAutoBlock ]) === false) {
                        $result[$scope]["failed"]++;
                        $result["errors"][] = [
                            "scope" => $scope,
                            "error" => "cantModifyFlat",
                            "flatId" => $flatId,
                            "contract" => $contract,
                            "targetAutoBlock" => (int)$targetAutoBlock,
                        ];
                        continue;
                    }

                    $result[$scope]["updated"]++;
                }
            }
        }
    }
