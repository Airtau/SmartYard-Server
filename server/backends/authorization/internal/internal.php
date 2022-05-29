<?php

    /**
     * backends authorization namespace
     */

    namespace backends\authorization {

        /**
         * allow-all security class
         */

        class internal extends authorization {

            /**
             * allow all
             *
             * @param object $params all params passed to api handlers
             * @return boolean allow or not
             */

            public function allow($params) {
                if ($params["_path"]["api"] === "authentication" && $params["_path"]["method"] === "login") {
                    return true;
                }

                if ($params["_uid"] === 0) {
                    return true;
                }

                try {
                    $sth = $this->db->prepare("
                        select count(*) as allow from core_api_methods where aid in (
                            select aid from (
                                select aid from core_api_methods where aid in (
                                    select aid from core_groups_rights where allow = 1 and gid in (
                                        select gid from core_users_groups where uid = :uid
                                     )
                                ) or aid in (select aid from core_api_methods_common)
                            ) as t1 where
                                aid not in (select aid from core_groups_rights where allow = 0 and gid in (select gid from core_users_groups where uid = :uid)) and
                                aid not in (select aid from core_users_rights where allow = 0 and uid = :uid)
                            union
                                select aid from core_api_methods where aid in (select aid from core_users_rights where allow = 1 and uid = :uid)
                        ) and api = :api and method = :method and request_method = :request_method"
                    );

                    if ($sth->execute([
                        ":uid" => $params["_uid"],
                        ":api" => $params["_path"]["api"],
                        ":method" => $params["_path"]["method"],
                        ":request_method" => $params["_request_method"],
                    ])) {
                        $m = $sth->fetchAll(\PDO::FETCH_ASSOC);
                        if ($m && $m[0] && $m[0]["allow"]) {
                            return true;
                        }
                        if (@$this->availableForSelf[$params["_path"]["api"]][$params["_path"]["method"]] && @in_array($params["_request_method"], $this->availableForSelf[$params["_path"]["api"]][$params["_path"]["method"]]) && $params["_id"] == $params["_uid"]) {
                            return true;
                        }
                    }
                } catch (\Exception $e) {
                    error_log(print_r($e, true));
                }

                return false;
            }

            /**
             * list of available methods for user
             *
             * @param integer $uid uid
             * @return array
             */

            public function allowedMethods($uid) {
                if (!checkInt($uid)) {
                    return false;
                }

                if ($uid === 0) {
                    return $this->methods();
                } else {
                    $m = [];
                    try {
                        $sth = $this->db->prepare("
                            select * from core_api_methods where aid in (
                                select aid from (
                                    select aid from core_api_methods where aid in (
                                        select aid from core_groups_rights where allow = 1 and gid in (
                                            select gid from core_users_groups where uid = :uid
                                         )
                                    ) or aid in (select aid from core_api_methods_common) or aid in (select aid from core_api_methods_personal)
                                ) as t1 where
                                    aid not in (select aid from core_groups_rights where allow = 0 and gid in (select gid from core_users_groups where uid = :uid)) and
                                    aid not in (select aid from core_users_rights where allow = 0 and uid = :uid)
                                union
                                    select aid from core_api_methods where aid in (select aid from core_users_rights where allow = 1 and uid = :uid)
                            )"
                        );

                        if ($sth->execute([
                            ":uid" => $uid,
                        ])) {
                            $all = $sth->fetchAll(\PDO::FETCH_ASSOC);
                            foreach ($all as $a) {
                                $m[$a['api']][$a['method']][$a['request_method']] = $a['aid'];
                            }
                        }
                    } catch (\Exception $e) {
                        error_log(print_r($e, true));
                        return false;
                    }

                    return $m;
                }
            }

            /**
             * @return array
             */

            public function getRights() {
                $users = $this->db->query("select uid, aid, allow from core_users_rights", \PDO::FETCH_ASSOC)->fetchAll();
                $groups = $this->db->query("select gid, aid, allow from core_groups_rights", \PDO::FETCH_ASSOC)->fetchAll();

                return [
                    "users" => $users,
                    "groups" => $groups,
                ];
            }

            /**
             * add, modify or delete user or group access to api method
             *
             * @param boolean $user user or group
             * @param integer $id uid or gid
             * @param string $api
             * @param string $method
             * @param string[] $allow
             * @param string[] $deny
             *
             * @return boolean
             */


            public function setRights($user, $id, $api, $method, $allow, $deny) {
                if (!checkInt($id)) {
                    return false;
                }

                if (!is_array($allow)) {
                    $allow = [ $allow ];
                }

                if (!is_array($deny)) {
                    $deny = [ $deny ];
                }

                $tn = $user?"core_users_rights":"core_groups_rights";
                $ci = $user?"uid":"gid";

                try {
                    $sth = $this->db->prepare("delete from $tn where aid in (select aid from core_api_methods where api = :api and method = :method)");
                    $sth->execute([
                        ":api" => $api,
                        ":method" => $method,
                    ]);
                } catch (\Exception $e) {
                    error_log(print_r($e));
                    return false;
                }

                try {
                    $sthI = $this->db->prepare("insert into $tn ($ci, aid, allow) values (:id, :aid, :allow)");
                } catch (\Exception $e) {
                    error_log(print_r($e));
                    return false;
                }

                foreach ($allow as $aid) {
                    try {
                        $sthI->execute([
                            ":id" => $id,
                            ":aid" => $aid,
                            ":allow" => 1,
                        ]);
                    } catch (\Exception $e) {
                        error_log(print_r($e));
                        return false;
                    }
                }

                foreach ($deny as $aid) {
                    try {
                        $sthI->execute([
                            ":id" => $id,
                            ":aid" => $aid,
                            ":allow" => 0,
                        ]);
                    } catch (\Exception $e) {
                        error_log(print_r($e));
                        return false;
                    }
                }

                return true;
            }

            public function capabilities() {
                return [
                    "mode" => "rw",
                ];
            }
        }
    }
