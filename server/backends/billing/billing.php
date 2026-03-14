<?php

/**
 * backends billing namespace
 */

namespace backends\billing {

    use backends\backend;

    /**
     * base billing class
     */

    abstract class billing extends backend {

        /**
         * returns basic subscriber account info from billing by login/password
         *
         * @param $login subscriber login in billing
         * @param $password subscriber password in billing
         * @return false|array
         * false if request/auth/parsing failed (error details are available via setLastError/getLastError)
         *
        */

        public abstract function getSubscriberAccountInfo($login, $password);

        /**
         * @param $items array of flat-contract links from billing
         * each item:
         * - houseId (required)
         * - flat (required)
         * - address (optional, debug)
         * - contract (required)
         * - login (optional)
         * - password (optional)
         * - autoBlock (optional, if set should be applied; if omitted should not be changed)
         *
         * @return boolean|array
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
         * imports address hierarchy from billing into RBT
         * required minimum is hierarchy up to house
         * optional: flats list for each house
         *
         * each item:
         * - regionUuid, region (required)
         * - areaUuid, area (optional)
         * - cityUuid, city (optional, at least one of area/city must be provided)
         * - settlementUuid, settlement (optional)
         * - streetUuid, street (optional, at least one of settlement/street must be provided)
         *   if settlement is omitted and street is provided, city is required
         * - houseUuid, house (required)
         * - houseFull (optional)
         * - services (optional) array of:
         *   "internet","iptv","ctv","phone","cctv","domophone","gsm"
         * - flats (optional) array of items:
         *   - flat or flatNumber (required)
         *   - floor (optional)
         *
         * @return boolean|array
         */
        function importAddressHierarchy($items) {
            if (!is_array($items)) {
                setLastError("invalidParams");
                return false;
            }

            $addresses = loadBackend("addresses");
            $households = loadBackend("households");
            $customFields = loadBackend("customFields");

            if (!$addresses || !$households || !$customFields) {
                return false;
            }

            $allowedServices = [
                "internet",
                "iptv",
                "ctv",
                "phone",
                "cctv",
                "domophone",
                "gsm",
            ];

            $result = [
                "processed" => 0,
                "invalid" => 0,
                "failed" => 0,
                "created" => [
                    "regions" => 0,
                    "areas" => 0,
                    "cities" => 0,
                    "settlements" => 0,
                    "streets" => 0,
                    "houses" => 0,
                    "flats" => 0,
                ],
                "skipped" => [
                    "regions" => 0,
                    "areas" => 0,
                    "cities" => 0,
                    "settlements" => 0,
                    "streets" => 0,
                    "houses" => 0,
                    "flats" => 0,
                ],
                "servicesUpdated" => 0,
                "uuidMismatches" => 0,
                "errors" => [],
            ];

            $cache = [
                "regions" => null,
                "areas" => [],
                "cities" => [],
                "settlements" => [],
                "streets" => [],
                "houses" => [],
                "flats" => [],
            ];

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

                $regionUuid = @$item["regionUuid"];
                $region = @$item["region"];
                $areaUuid = @$item["areaUuid"];
                $area = @$item["area"];
                $cityUuid = @$item["cityUuid"];
                $city = @$item["city"];
                $settlementUuid = @$item["settlementUuid"];
                $settlement = @$item["settlement"];
                $streetUuid = @$item["streetUuid"];
                $street = @$item["street"];
                $houseUuid = @$item["houseUuid"];
                $house = @$item["house"];

                if (!checkStr($regionUuid) || $regionUuid === "" || !checkStr($region) || $region === "" ||
                    !checkStr($houseUuid) || $houseUuid === "" || !checkStr($house) || $house === "") {
                    $result["invalid"]++;
                    $result["errors"][] = [
                        "index" => $index,
                        "error" => "invalidRequiredFields",
                    ];
                    continue;
                }

                $hasArea = checkStr($areaUuid) && $areaUuid !== "" && checkStr($area) && $area !== "";
                $hasCity = checkStr($cityUuid) && $cityUuid !== "" && checkStr($city) && $city !== "";
                $hasSettlement = checkStr($settlementUuid) && $settlementUuid !== "" && checkStr($settlement) && $settlement !== "";
                $hasStreet = checkStr($streetUuid) && $streetUuid !== "" && checkStr($street) && $street !== "";

                if (!$hasArea && !$hasCity) {
                    $result["invalid"]++;
                    $result["errors"][] = [
                        "index" => $index,
                        "error" => "missingAreaOrCity",
                        "houseUuid" => $houseUuid,
                    ];
                    continue;
                }

                if (!$hasSettlement && !$hasStreet) {
                    $result["invalid"]++;
                    $result["errors"][] = [
                        "index" => $index,
                        "error" => "missingSettlementOrStreet",
                        "houseUuid" => $houseUuid,
                    ];
                    continue;
                }

                if ($hasStreet && !$hasSettlement && !$hasCity) {
                    $result["invalid"]++;
                    $result["errors"][] = [
                        "index" => $index,
                        "error" => "streetRequiresCityOrSettlement",
                        "houseUuid" => $houseUuid,
                    ];
                    continue;
                }

                $regionIsoCode = @$item["regionIsoCode"];
                $regionWithType = @$item["regionWithType"];
                $regionType = @$item["regionType"];
                $regionTypeFull = @$item["regionTypeFull"];
                $timezone = @$item["timezone"];

                if (!checkStr($regionIsoCode)) {
                    $regionIsoCode = "";
                }
                if (!checkStr($regionWithType) || $regionWithType === "") {
                    $regionWithType = $region;
                }
                if (!checkStr($regionType)) {
                    $regionType = "";
                }
                if (!checkStr($regionTypeFull)) {
                    $regionTypeFull = "";
                }
                if (!checkStr($timezone) || $timezone === "") {
                    $timezone = "-";
                }

                $areaWithType = @$item["areaWithType"];
                $areaType = @$item["areaType"];
                $areaTypeFull = @$item["areaTypeFull"];
                if (!checkStr($areaWithType) || $areaWithType === "") {
                    $areaWithType = $hasArea ? $area : "";
                }
                if (!checkStr($areaType)) {
                    $areaType = "";
                }
                if (!checkStr($areaTypeFull)) {
                    $areaTypeFull = "";
                }

                $cityWithType = @$item["cityWithType"];
                $cityType = @$item["cityType"];
                $cityTypeFull = @$item["cityTypeFull"];
                if (!checkStr($cityWithType) || $cityWithType === "") {
                    $cityWithType = $hasCity ? $city : "";
                }
                if (!checkStr($cityType)) {
                    $cityType = "";
                }
                if (!checkStr($cityTypeFull)) {
                    $cityTypeFull = "";
                }

                $settlementWithType = @$item["settlementWithType"];
                $settlementType = @$item["settlementType"];
                $settlementTypeFull = @$item["settlementTypeFull"];
                if (!checkStr($settlementWithType) || $settlementWithType === "") {
                    $settlementWithType = $hasSettlement ? $settlement : "";
                }
                if (!checkStr($settlementType)) {
                    $settlementType = "";
                }
                if (!checkStr($settlementTypeFull)) {
                    $settlementTypeFull = "";
                }

                $streetWithType = @$item["streetWithType"];
                $streetType = @$item["streetType"];
                $streetTypeFull = @$item["streetTypeFull"];
                if (!checkStr($streetWithType) || $streetWithType === "") {
                    $streetWithType = $hasStreet ? $street : "";
                }
                if (!checkStr($streetType)) {
                    $streetType = "";
                }
                if (!checkStr($streetTypeFull)) {
                    $streetTypeFull = "";
                }

                $houseType = @$item["houseType"];
                $houseTypeFull = @$item["houseTypeFull"];
                $houseFull = @$item["houseFull"];
                $companyId = @$item["companyId"];

                if (!checkStr($houseType)) {
                    $houseType = "";
                }
                if (!checkStr($houseTypeFull)) {
                    $houseTypeFull = "";
                }
                if (!checkStr($houseFull) || $houseFull === "") {
                    $houseFull = $house;
                }
                if ($companyId === null || $companyId === "") {
                    $companyId = 0;
                }
                if (!checkInt($companyId)) {
                    $result["invalid"]++;
                    $result["errors"][] = [
                        "index" => $index,
                        "error" => "invalidCompanyId",
                        "houseUuid" => $houseUuid,
                    ];
                    continue;
                }

                $services = null;
                if (array_key_exists("services", $item)) {
                    if (!is_array($item["services"])) {
                        $result["invalid"]++;
                        $result["errors"][] = [
                            "index" => $index,
                            "error" => "invalidServices",
                            "houseUuid" => $houseUuid,
                        ];
                        continue;
                    }

                    $services = [];

                    foreach ($item["services"] as $service) {
                        if (!checkStr($service) || $service === "") {
                            $result["invalid"]++;
                            $result["errors"][] = [
                                "index" => $index,
                                "error" => "invalidServiceValue",
                                "houseUuid" => $houseUuid,
                            ];
                            continue 2;
                        }

                        $service = mb_strtolower($service);

                        if (!in_array($service, $allowedServices, true)) {
                            $result["invalid"]++;
                            $result["errors"][] = [
                                "index" => $index,
                                "error" => "unknownService",
                                "houseUuid" => $houseUuid,
                                "service" => $service,
                            ];
                            continue 2;
                        }

                        $services[$service] = $service;
                    }

                    $services = array_values($services);
                    sort($services, SORT_STRING);
                }

                if ($cache["regions"] === null) {
                    $cache["regions"] = $addresses->getRegions();

                    if (!is_array($cache["regions"])) {
                        $result["failed"]++;
                        $result["errors"][] = [
                            "index" => $index,
                            "error" => "cantGetRegions",
                        ];
                        continue;
                    }
                }

                $regionMatch = $this->findAddressItemMatch($cache["regions"], "regionUuid", $regionUuid, "region", $region);

                if ($regionMatch === false) {
                    $regionId = $addresses->addRegion($regionUuid, $regionIsoCode, $regionWithType, $regionType, $regionTypeFull, $region, $timezone);

                    if ($regionId === false || !checkInt($regionId) || !$regionId) {
                        $result["failed"]++;
                        $result["errors"][] = [
                            "index" => $index,
                            "error" => "cantAddRegion",
                            "regionUuid" => $regionUuid,
                            "region" => $region,
                        ];
                        continue;
                    }

                    $regionRow = $addresses->getRegion($regionId);

                    if (!is_array($regionRow)) {
                        $result["failed"]++;
                        $result["errors"][] = [
                            "index" => $index,
                            "error" => "cantGetRegion",
                            "regionId" => $regionId,
                        ];
                        continue;
                    }

                    $cache["regions"][] = $regionRow;
                    $result["created"]["regions"]++;
                } else {
                    $regionRow = $regionMatch["row"];
                    $regionId = @$regionRow["regionId"];

                    if (!checkInt($regionId) || !$regionId) {
                        $result["failed"]++;
                        $result["errors"][] = [
                            "index" => $index,
                            "error" => "invalidRegionId",
                            "regionUuid" => $regionUuid,
                        ];
                        continue;
                    }

                    $result["skipped"]["regions"]++;

                    if ($regionMatch["by"] === "name") {
                        $result["uuidMismatches"]++;
                        $result["errors"][] = [
                            "index" => $index,
                            "error" => "regionUuidMismatch",
                            "expectedUuid" => $regionUuid,
                            "actualUuid" => @$regionRow["regionUuid"],
                            "regionId" => $regionId,
                        ];
                    }
                }

                $areaId = 0;
                if ($hasArea) {
                    if (!array_key_exists($regionId, $cache["areas"])) {
                        $cache["areas"][$regionId] = $addresses->getAreas($regionId);

                        if (!is_array($cache["areas"][$regionId])) {
                            $result["failed"]++;
                            $result["errors"][] = [
                                "index" => $index,
                                "error" => "cantGetAreas",
                                "regionId" => $regionId,
                            ];
                            continue;
                        }
                    }

                    $areaMatch = $this->findAddressItemMatch($cache["areas"][$regionId], "areaUuid", $areaUuid, "area", $area);

                    if ($areaMatch === false) {
                        $areaId = $addresses->addArea($regionId, $areaUuid, $areaWithType, $areaType, $areaTypeFull, $area, $timezone);

                        if ($areaId === false || !checkInt($areaId) || !$areaId) {
                            $result["failed"]++;
                            $result["errors"][] = [
                                "index" => $index,
                                "error" => "cantAddArea",
                                "areaUuid" => $areaUuid,
                                "area" => $area,
                                "regionId" => $regionId,
                            ];
                            continue;
                        }

                        $areaRow = $addresses->getArea($areaId);

                        if (!is_array($areaRow)) {
                            $result["failed"]++;
                            $result["errors"][] = [
                                "index" => $index,
                                "error" => "cantGetArea",
                                "areaId" => $areaId,
                            ];
                            continue;
                        }

                        $cache["areas"][$regionId][] = $areaRow;
                        $result["created"]["areas"]++;
                    } else {
                        $areaRow = $areaMatch["row"];
                        $areaId = @$areaRow["areaId"];

                        if (!checkInt($areaId) || !$areaId) {
                            $result["failed"]++;
                            $result["errors"][] = [
                                "index" => $index,
                                "error" => "invalidAreaId",
                                "areaUuid" => $areaUuid,
                            ];
                            continue;
                        }

                        $result["skipped"]["areas"]++;

                        if ($areaMatch["by"] === "name") {
                            $result["uuidMismatches"]++;
                            $result["errors"][] = [
                                "index" => $index,
                                "error" => "areaUuidMismatch",
                                "expectedUuid" => $areaUuid,
                                "actualUuid" => @$areaRow["areaUuid"],
                                "areaId" => $areaId,
                            ];
                        }
                    }
                }

                $cityId = 0;
                if ($hasCity) {
                    $citiesKey = $areaId ? "area:$areaId" : "region:$regionId";

                    if (!array_key_exists($citiesKey, $cache["cities"])) {
                        $cache["cities"][$citiesKey] = $areaId ? $addresses->getCities(false, $areaId) : $addresses->getCities($regionId, false);

                        if (!is_array($cache["cities"][$citiesKey])) {
                            $result["failed"]++;
                            $result["errors"][] = [
                                "index" => $index,
                                "error" => "cantGetCities",
                                "parent" => $citiesKey,
                            ];
                            continue;
                        }
                    }

                    $cityMatch = $this->findAddressItemMatch($cache["cities"][$citiesKey], "cityUuid", $cityUuid, "city", $city);

                    if ($cityMatch === false) {
                        $cityId = $addresses->addCity($areaId ? 0 : $regionId, $areaId ?: 0, $cityUuid, $cityWithType, $cityType, $cityTypeFull, $city, $timezone);

                        if ($cityId === false || !checkInt($cityId) || !$cityId) {
                            $result["failed"]++;
                            $result["errors"][] = [
                                "index" => $index,
                                "error" => "cantAddCity",
                                "cityUuid" => $cityUuid,
                                "city" => $city,
                                "parent" => $citiesKey,
                            ];
                            continue;
                        }

                        $cityRow = $addresses->getCity($cityId);

                        if (!is_array($cityRow)) {
                            $result["failed"]++;
                            $result["errors"][] = [
                                "index" => $index,
                                "error" => "cantGetCity",
                                "cityId" => $cityId,
                            ];
                            continue;
                        }

                        $cache["cities"][$citiesKey][] = $cityRow;
                        $result["created"]["cities"]++;
                    } else {
                        $cityRow = $cityMatch["row"];
                        $cityId = @$cityRow["cityId"];

                        if (!checkInt($cityId) || !$cityId) {
                            $result["failed"]++;
                            $result["errors"][] = [
                                "index" => $index,
                                "error" => "invalidCityId",
                                "cityUuid" => $cityUuid,
                            ];
                            continue;
                        }

                        $result["skipped"]["cities"]++;

                        if ($cityMatch["by"] === "name") {
                            $result["uuidMismatches"]++;
                            $result["errors"][] = [
                                "index" => $index,
                                "error" => "cityUuidMismatch",
                                "expectedUuid" => $cityUuid,
                                "actualUuid" => @$cityRow["cityUuid"],
                                "cityId" => $cityId,
                            ];
                        }
                    }
                }

                $settlementId = 0;
                if ($hasSettlement) {
                    $settlementsKey = $cityId ? "city:$cityId" : "area:$areaId";

                    if (!array_key_exists($settlementsKey, $cache["settlements"])) {
                        $cache["settlements"][$settlementsKey] = $cityId ? $addresses->getSettlements(false, $cityId) : $addresses->getSettlements($areaId, false);

                        if (!is_array($cache["settlements"][$settlementsKey])) {
                            $result["failed"]++;
                            $result["errors"][] = [
                                "index" => $index,
                                "error" => "cantGetSettlements",
                                "parent" => $settlementsKey,
                            ];
                            continue;
                        }
                    }

                    $settlementMatch = $this->findAddressItemMatch($cache["settlements"][$settlementsKey], "settlementUuid", $settlementUuid, "settlement", $settlement);

                    if ($settlementMatch === false) {
                        $settlementId = $addresses->addSettlement($cityId ? 0 : $areaId, $cityId ?: 0, $settlementUuid, $settlementWithType, $settlementType, $settlementTypeFull, $settlement);

                        if ($settlementId === false || !checkInt($settlementId) || !$settlementId) {
                            $result["failed"]++;
                            $result["errors"][] = [
                                "index" => $index,
                                "error" => "cantAddSettlement",
                                "settlementUuid" => $settlementUuid,
                                "settlement" => $settlement,
                                "parent" => $settlementsKey,
                            ];
                            continue;
                        }

                        $settlementRow = $addresses->getSettlement($settlementId);

                        if (!is_array($settlementRow)) {
                            $result["failed"]++;
                            $result["errors"][] = [
                                "index" => $index,
                                "error" => "cantGetSettlement",
                                "settlementId" => $settlementId,
                            ];
                            continue;
                        }

                        $cache["settlements"][$settlementsKey][] = $settlementRow;
                        $result["created"]["settlements"]++;
                    } else {
                        $settlementRow = $settlementMatch["row"];
                        $settlementId = @$settlementRow["settlementId"];

                        if (!checkInt($settlementId) || !$settlementId) {
                            $result["failed"]++;
                            $result["errors"][] = [
                                "index" => $index,
                                "error" => "invalidSettlementId",
                                "settlementUuid" => $settlementUuid,
                            ];
                            continue;
                        }

                        $result["skipped"]["settlements"]++;

                        if ($settlementMatch["by"] === "name") {
                            $result["uuidMismatches"]++;
                            $result["errors"][] = [
                                "index" => $index,
                                "error" => "settlementUuidMismatch",
                                "expectedUuid" => $settlementUuid,
                                "actualUuid" => @$settlementRow["settlementUuid"],
                                "settlementId" => $settlementId,
                            ];
                        }
                    }
                }

                $streetId = 0;
                if ($hasStreet) {
                    $streetsKey = $settlementId ? "settlement:$settlementId" : "city:$cityId";

                    if (!array_key_exists($streetsKey, $cache["streets"])) {
                        $cache["streets"][$streetsKey] = $settlementId ? $addresses->getStreets(false, $settlementId) : $addresses->getStreets($cityId, false);

                        if (!is_array($cache["streets"][$streetsKey])) {
                            $result["failed"]++;
                            $result["errors"][] = [
                                "index" => $index,
                                "error" => "cantGetStreets",
                                "parent" => $streetsKey,
                            ];
                            continue;
                        }
                    }

                    $streetMatch = $this->findAddressItemMatch($cache["streets"][$streetsKey], "streetUuid", $streetUuid, "street", $street);

                    if ($streetMatch === false) {
                        $streetId = $addresses->addStreet($settlementId ? 0 : $cityId, $settlementId ?: 0, $streetUuid, $streetWithType, $streetType, $streetTypeFull, $street);

                        if ($streetId === false || !checkInt($streetId) || !$streetId) {
                            $result["failed"]++;
                            $result["errors"][] = [
                                "index" => $index,
                                "error" => "cantAddStreet",
                                "streetUuid" => $streetUuid,
                                "street" => $street,
                                "parent" => $streetsKey,
                            ];
                            continue;
                        }

                        $streetRow = $addresses->getStreet($streetId);

                        if (!is_array($streetRow)) {
                            $result["failed"]++;
                            $result["errors"][] = [
                                "index" => $index,
                                "error" => "cantGetStreet",
                                "streetId" => $streetId,
                            ];
                            continue;
                        }

                        $cache["streets"][$streetsKey][] = $streetRow;
                        $result["created"]["streets"]++;
                    } else {
                        $streetRow = $streetMatch["row"];
                        $streetId = @$streetRow["streetId"];

                        if (!checkInt($streetId) || !$streetId) {
                            $result["failed"]++;
                            $result["errors"][] = [
                                "index" => $index,
                                "error" => "invalidStreetId",
                                "streetUuid" => $streetUuid,
                            ];
                            continue;
                        }

                        $result["skipped"]["streets"]++;

                        if ($streetMatch["by"] === "name") {
                            $result["uuidMismatches"]++;
                            $result["errors"][] = [
                                "index" => $index,
                                "error" => "streetUuidMismatch",
                                "expectedUuid" => $streetUuid,
                                "actualUuid" => @$streetRow["streetUuid"],
                                "streetId" => $streetId,
                            ];
                        }
                    }
                }

                $housesKey = $streetId ? "street:$streetId" : "settlement:$settlementId";

                if (!array_key_exists($housesKey, $cache["houses"])) {
                    $cache["houses"][$housesKey] = $streetId ? $addresses->getHouses(false, $streetId) : $addresses->getHouses($settlementId, false);

                    if (!is_array($cache["houses"][$housesKey])) {
                        $result["failed"]++;
                        $result["errors"][] = [
                            "index" => $index,
                            "error" => "cantGetHouses",
                            "parent" => $housesKey,
                        ];
                        continue;
                    }
                }

                $houseMatch = $this->findAddressItemMatch($cache["houses"][$housesKey], "houseUuid", $houseUuid, "house", $house);

                if ($houseMatch === false) {
                    $houseId = $addresses->addHouse($streetId ? 0 : $settlementId, $streetId ?: 0, $houseUuid, $houseType, $houseTypeFull, $houseFull, $house, $companyId);

                    if ($houseId === false || !checkInt($houseId) || !$houseId) {
                        $result["failed"]++;
                        $result["errors"][] = [
                            "index" => $index,
                            "error" => "cantAddHouse",
                            "houseUuid" => $houseUuid,
                            "house" => $house,
                            "parent" => $housesKey,
                        ];
                        continue;
                    }

                    $houseRow = $addresses->getHouse($houseId);

                    if (!is_array($houseRow)) {
                        $result["failed"]++;
                        $result["errors"][] = [
                            "index" => $index,
                            "error" => "cantGetHouse",
                            "houseId" => $houseId,
                        ];
                        continue;
                    }

                    $cache["houses"][$housesKey][] = $houseRow;
                    $result["created"]["houses"]++;
                } else {
                    $houseRow = $houseMatch["row"];
                    $houseId = @$houseRow["houseId"];

                    if (!checkInt($houseId) || !$houseId) {
                        $result["failed"]++;
                        $result["errors"][] = [
                            "index" => $index,
                            "error" => "invalidHouseId",
                            "houseUuid" => $houseUuid,
                        ];
                        continue;
                    }

                    $result["skipped"]["houses"]++;

                    if ($houseMatch["by"] === "name") {
                        $result["uuidMismatches"]++;
                        $result["errors"][] = [
                            "index" => $index,
                            "error" => "houseUuidMismatch",
                            "expectedUuid" => $houseUuid,
                            "actualUuid" => @$houseRow["houseUuid"],
                            "houseId" => $houseId,
                        ];
                    }
                }

                if ($services !== null) {
                    $values = $customFields->getValues("house", $houseId);
                    if (!is_array($values)) {
                        $values = [];
                    }

                    $servicesValue = implode(",", $services);
                    $currentServicesValue = @$values["services"];

                    if ($currentServicesValue !== $servicesValue) {
                        $values["services"] = $servicesValue;

                        if ($customFields->modifyValues("house", $houseId, $values) === false) {
                            $result["failed"]++;
                            $result["errors"][] = [
                                "index" => $index,
                                "error" => "cantModifyHouseServices",
                                "houseId" => $houseId,
                                "houseUuid" => $houseUuid,
                            ];
                        } else {
                            $result["servicesUpdated"]++;
                        }
                    }
                }

                if (array_key_exists("flats", $item)) {
                    if (!is_array($item["flats"])) {
                        $result["invalid"]++;
                        $result["errors"][] = [
                            "index" => $index,
                            "error" => "invalidFlats",
                            "houseId" => $houseId,
                            "houseUuid" => $houseUuid,
                        ];
                        continue;
                    }

                    if (!array_key_exists($houseId, $cache["flats"])) {
                        $cache["flats"][$houseId] = $households->getFlats("houseId", $houseId);

                        if (!is_array($cache["flats"][$houseId])) {
                            $result["failed"]++;
                            $result["errors"][] = [
                                "index" => $index,
                                "error" => "cantGetFlats",
                                "houseId" => $houseId,
                            ];
                            continue;
                        }
                    }

                    $houseFlatNumbers = [];
                    foreach ($cache["flats"][$houseId] as $houseFlat) {
                        $_flat = @$houseFlat["flat"];
                        if (checkStr($_flat) && $_flat !== "") {
                            $houseFlatNumbers[$_flat] = $_flat;
                        }
                    }

                    foreach ($item["flats"] as $flatIndex => $flatItem) {
                        $flatNumber = null;
                        $floor = 0;

                        if (is_array($flatItem)) {
                            $flatNumber = array_key_exists("flat", $flatItem) ? $flatItem["flat"] : @$flatItem["flatNumber"];
                            $floor = array_key_exists("floor", $flatItem) ? $flatItem["floor"] : 0;
                        } else {
                            $flatNumber = $flatItem;
                        }

                        if (!checkStr($flatNumber) || $flatNumber === "") {
                            $result["failed"]++;
                            $result["errors"][] = [
                                "index" => $index,
                                "flatIndex" => $flatIndex,
                                "error" => "invalidFlat",
                                "houseId" => $houseId,
                                "houseUuid" => $houseUuid,
                            ];
                            continue;
                        }

                        if (!checkInt($floor)) {
                            $floor = 0;
                        }

                        if (array_key_exists($flatNumber, $houseFlatNumbers)) {
                            $result["skipped"]["flats"]++;
                            continue;
                        }

                        $flatId = $households->addFlat($houseId, $floor, $flatNumber, "", [], [], 0, 0, false, 0, 0, 0, 0, "", "");

                        if ($flatId === false || !checkInt($flatId) || !$flatId) {
                            $result["failed"]++;
                            $result["errors"][] = [
                                "index" => $index,
                                "flatIndex" => $flatIndex,
                                "error" => "cantAddFlat",
                                "houseId" => $houseId,
                                "houseUuid" => $houseUuid,
                                "flat" => $flatNumber,
                            ];
                            continue;
                        }

                        $houseFlatNumbers[$flatNumber] = $flatNumber;
                        $result["created"]["flats"]++;
                    }

                    $cache["flats"][$houseId] = $households->getFlats("houseId", $houseId);
                }
            }

            return $result;
        }

        /**
         * full subscribers sync for flats contract/autoBlock/customFields
         *
         * @param $subscribers array of subscribers
         * each item:
         * - subscriberID (required, numeric, stored to flat.contract)
         * - agreement (required, string, stored to custom field agreement)
         * - isActive (required, bool/int, if true -> autoBlock = 0, else autoBlock = 1)
         * - addressText (required, string, stored to custom field addressText)
         * - buildingUUID (required, string)
         * - flatNumber (required, string)
         * @param $defaultAction string
         * values:
         * - "skipMissing" (default): do not change subscribers not in list
         * - "blockMissing": block subscribers not in list (autoBlock = 1)
         * - "unblockMissing": unblock subscribers not in list (autoBlock = 0)
         *
         * @return boolean|array
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

        private function findAddressItemMatch($rows, $uuidField, $uuid, $nameField, $name) {
            foreach ($rows as $row) {
                $_uuid = @$row[$uuidField];

                if (checkStr($_uuid) && $_uuid !== "" && $_uuid === $uuid) {
                    return [
                        "by" => "uuid",
                        "row" => $row,
                    ];
                }
            }

            foreach ($rows as $row) {
                $_name = @$row[$nameField];

                if (checkStr($_name) && $_name !== "" && $_name === $name) {
                    return [
                        "by" => "name",
                        "row" => $row,
                    ];
                }
            }

            return false;
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
