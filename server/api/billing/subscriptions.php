<?php

    /**
     * @api {get} /api/billing/subscriptions synchronize active contracts for auto-blocking
     *
     * @apiVersion 1.0.0
     *
     * @apiName subscriptions
     * @apiGroup subscriptions
     *
     * @apiHeader {String} Authorization authentication token
     *
     * @apiParam {Object[]} subscribers list of subscribers with active contracts
        * @apiParam {Number} subscribers.subscriberID subscriber ID
        * @apiParam {String} subscribers.agreement agreement number
        * @apiParam {Boolean} subscribers.isActive is active contract or not
        * @apiParam {String} subscribers.addressText address text
        * @apiParam {String} subscribers.buildingUUID building UUID
        * @apiParam {String} subscribers.flatNumber flat number
     * @apiSuccess {Object} subscriptions synchronization result
     * @apiSuccess {Number} subscriptions.processed total processed subscriber items
     * @apiSuccess {Number} subscriptions.updated successfully updated flats
     * @apiSuccess {Number} subscriptions.invalid invalid subscriber items
     * @apiSuccess {Number} subscriptions.notFound subscribers not matched to any flat
     * @apiSuccess {Number} subscriptions.failed internal processing errors count
     * @apiSuccess {String} subscriptions.defaultAction default action for missing contracts (`skipMissing|blockMissing|unblockMissing`)
     * @apiSuccess {Object} subscriptions.missing result for contracts not present in request
     * @apiSuccess {Number} subscriptions.missing.updated count of missing contracts with updated autoBlock
     * @apiSuccess {Number} subscriptions.missing.unchanged count of missing contracts left unchanged
     * @apiSuccess {Number} subscriptions.missing.failed count of missing contracts failed to update
     * @apiSuccess {Object[]} subscriptions.errors list of validation/runtime errors
     */

    /**
     * billing api
     */

    namespace api\billing {

        use api\api;

        /**
         * subscriptions method
         */

        class subscriptions extends api {

            public static function POST($params) {
                // Method for synchronizing the list of active contracts for auto-blocking
                $response = false;

                if (
                    array_key_exists("subscribers", $params) &&
                    is_array($params['subscribers'])
                ) {
                    $billing = loadBackend("billing");
                    if (!$billing) {
                        return "error";
                    }   

                    $_subscribers = [];

                    foreach ($params['subscribers'] as $index => $subscriber) {
                        if (!array_key_exists("subscriberID", $subscriber) || !is_numeric($subscriber['subscriberID'])) {
                            return "subscriberID is required for subscriber at index " . $index;
                        }
                        $subscriberID = intval($subscriber['subscriberID']);
                        
                        if (!array_key_exists("agreement", $subscriber) || !is_string($subscriber['agreement'])) {
                            return "agreement is required for subscriber at index " . $index;
                        }
                        $agreement = $subscriber['agreement'];

                        if (!array_key_exists("isActive", $subscriber) || !is_bool($subscriber['isActive'])) {
                            return "isActive is required for subscriber at index " . $index;
                        }
                        $isActive = $subscriber['isActive'];

                        if (!array_key_exists("addressText", $subscriber) || !is_string($subscriber['addressText'])) {
                            return "addressText is required for subscriber at index " . $index;
                        }
                        $addressText = $subscriber['addressText'];

                        if (!array_key_exists("buildingUUID", $subscriber) || !is_string($subscriber['buildingUUID'])) {
                            return "buildingUUID is required for subscriber at index " . $index;
                        }
                        $buildingUUID = $subscriber['buildingUUID'];

                        if (!array_key_exists("flatNumber", $subscriber) || !is_string($subscriber['flatNumber'])) {
                            return "flatNumber is required for subscriber at index " . $index;
                        }
                        $flatNumber = $subscriber['flatNumber'];

                        $_subscribers[] = [
                            "subscriberID" => $subscriberID,
                            "agreement" => $agreement,
                            "isActive" => $isActive,
                            "addressText" => $addressText,
                            "buildingUUID" => $buildingUUID,
                            "flatNumber" => $flatNumber
                        ];
                    }

                    $response = $billing->syncAutoBlockByContracts($_subscribers, "skipMissing");
                }
                
                return api::ANSWER($response, ($response !== false) ? "subscriptions" : false);
            }
        }
    }
