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
        }
    }
