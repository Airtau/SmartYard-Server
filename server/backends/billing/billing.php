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

        abstract function setContractsBindings($items);

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

        abstract function syncAutoBlockByContracts($subscribers, $defaultAction = "skipMissing");
    }
}
