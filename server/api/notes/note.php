<?php

    /**
     * notes api
     */

    namespace api\notes {

        use api\api;

        /**
         * note method
         */

        class note extends api {

            public static function POST($params) {
                $notes = loadBackend("notes");

                if ($notes) {
                    $success = $notes->addNote(@$params["subject"], @$params["body"], @$params["checks"], @$params["category"], @$params["remind"], @$params["icon"], @$params["font"], @$params["color"]);
                }

                return api::ANSWER($success);
            }

            public static function PUT($params) {
                $notes = loadBackend("notes");

                if ($notes) {
                    $success = $notes->modifyNote(@$params["_id"], @$params["subject"], @$params["body"], @$params["category"], @$params["remind"], @$params["icon"], @$params["font"], @$params["color"], @$params["x"], @$params["y"], @$params["z"]);
                }

                return api::ANSWER($success);
            }

            public static function DELETE($params) {
                $notes = loadBackend("notes");

                if ($notes) {
                    $success = $notes->deleteNote(@$params["_id"]);
                }

                return api::ANSWER($success);
            }

            public static function index() {
                return [
                    "POST" => "#common",
                    "PUT" => "#common",
                    "DELETE" => "#common",
                ];
            }
        }
    }
