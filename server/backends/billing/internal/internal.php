<?php

    /**
     * backends billing namespace
     */

    namespace backends\billing {

        /**
         * internal billing class
         */

        class internal extends billing {

            use soapClient;
            use customHelpers;

            /**
             * @inheritDoc
             */

            public function getSubscriberAccountInfo($login, $password) {
                return false;
            }

            /**
             * @inheritDoc
             */

            public function getSubscriberAdditionalServices($login, $password, $agrmid) {
                return false;
            }
        }
    }
