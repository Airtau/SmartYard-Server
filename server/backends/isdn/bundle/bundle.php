<?php

    /**
     * backends isdn namespace
     */

    namespace backends\isdn {

        /**
         * LanTa's variant of flash calls and sms sending
         */

        require_once __DIR__ . "/../.traits/sms.php";
        require_once __DIR__ . "/../.traits/incoming.php";

        class bundle extends isdn {
            use sms, incoming;

            /**
             * @inheritDoc
             */

            function pushLanta($push) {
                $query = "";
                foreach ($push as $param => $value) {
                    if ($param != "action" && $param != "secret" && $param != "video") {
                        $query = $query . $param . "=" . urlencode($value) . "&";
                    }
                    if ($param == "action") {
                        $query = $query . "pushAction=" . urlencode($value) . "&";
                    }
                    if ($param == "video") {
                        $query = $query . "video=" . urlencode(json_encode($value)) . "&";
                    }
                }
                if ($query) {
                    $query = substr($query, 0, -1);
                }

                $result = trim(file_get_contents("https://isdn.lanta.me/isdn_api.php?action=push&secret=" . $this->config["backends"]["isdn"]["common_secret"] . "&" . $query));

                if (strtolower(explode(":", $result)[0]) !== "ok") {
                    error_log("isdn push send error:\n query = $query\n result = $result\n");

                    if (strtolower($result) === "err:broken") {
                        loadBackend("households")->dismissToken($push["token"]);
                    }
                }

                return $result;
            }

            function push($push) {
                return $this->pushLanta($push);
            }
        }
    }
