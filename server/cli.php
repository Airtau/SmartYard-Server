<?php

// command line client

    chdir(__DIR__);

    $cli = true;
    $cliError = false;

    require_once "utils/error.php";
    require_once "utils/response.php";
    require_once "utils/hooks.php";
    require_once "utils/guidv4.php";
    require_once "utils/loader.php";
    require_once "utils/checkint.php";
    require_once "utils/purifier.php";
    require_once "utils/checkstr.php";
    require_once "utils/email.php";
    require_once "utils/is_executable.php";
    require_once "utils/db_ext.php";
    require_once "utils/parse_url_ext.php";
    require_once "utils/debug.php";
    require_once "utils/i18n.php";

    require_once "backends/backend.php";

    require_once "api/api.php";

    function usage() {
        global $argv;

        echo "usage: {$argv[0]}
        backend:
            [<backend name> [params]]

        common parts:
            [--parent-pid=<pid>]
            [--debug]

        demo server:
            [--run-demo-server [--port=<port>]]

        initialization:
            [--init-db [--skip=<versions>]]
            [--admin-password=<password>]
            [--reindex]
            [--clear-cache]
            [--cleanup]
            [--init-mobile-issues-project]

        tests:
            [--check-mail=<your email address>]
            [--get-db-version]

        backends:
            [--backends-with-cli]
            [--check-backends]

        autoconfigure:
            [--autoconfigure-domophone=<domophone_id> [--first-time]]
            [--autoconfigure-device=<device_type> --id=<device_id> [--first-time]]

        cron:
            [--cron=<minutely|5min|hourly|daily|monthly>]
            [--install-crontabs]
            [--uninstall-crontabs]

        dvr:
            [--run-record-download=<id>]

        config:
            [--print-config]
            [--write-yaml-config]
            [--write-json-config]
        \n";

        exit(1);
    }

    $script_result = null;
    $script_process_id = -1;
    $script_filename = __FILE__;
    $script_parent_pid = null;

    $args = [];

    for ($i = 1; $i < count($argv); $i++) {
        $a = explode('=', $argv[$i]);
        if ($a[0] == '--parent-pid') {
            if (!checkInt($a[1])) {
                usage();
            } else {
                $script_parent_pid = $a[1];
            }
        } else
        if ($a[0] == '--debug' && !isset($a[1])) {
            debugOn();
        }
        else {
            $args[$a[0]] = @$a[1];
        }
    }

    $params = '';
    foreach ($args as $key => $value) {
        if ($value) {
            $params .= " {$key}={$value}";
        } else {
            $params .= " {$key}";
        }
    }

    function startup() {
        global $db, $params, $script_process_id, $script_parent_pid;

        if (@$db) {
            try {
                $script_process_id = $db->insert('insert into core_running_processes (pid, ppid, start, process, params, expire) values (:pid, :ppid, :start, :process, :params, :expire)', [
                    "pid" => getmypid(),
                    "ppid" => $script_parent_pid,
                    "start" => time(),
                    "process" => "cli.php",
                    "params" => $params,
                    "expire" => time() + 24 * 60 * 60,
                ], [ "silent" ]);
            } catch (\Exception $e) {
                //
            }
        }
    }

    function shutdown() {
        global $script_process_id, $db, $script_result;

        if (@$db) {
            try {
                $db->modify("update core_running_processes set done = :done, result = :result where running_process_id = :running_process_id", [
                    "done" => time(),
                    "result" => $script_result,
                    "running_process_id" => $script_process_id,
                ], [ "silent" ]);
            } catch (\Exception $e) {
                //
            }
        }
    }

    function check_if_pid_exists() {
        global $db;

        if (@$db) {
            try {
                $pids = $db->get("select running_process_id, pid from core_running_processes where done is null", false, [
                    "running_process_id" => "id",
                    "pid" => "pid",
                ], [ "silent" ]);

                if ($pids) {
                    foreach ($pids as $process) {
                        if (!file_exists( "/proc/{$process['pid']}")) {
                            $db->modify("update core_running_processes set done = :done, result = :result where running_process_id = :running_process_id", [
                                "done" => time(),
                                "result" => "unknown",
                                "running_process_id" => $process['id'],
                            ], [ "silent" ]);
                        }
                    }
                }
            } catch (\Exception $e) {
                //
            }
        }
    }

    register_shutdown_function('shutdown');

    if ((count($args) == 1 || count($args) == 2) && array_key_exists("--run-demo-server", $args) && !isset($args["--run-demo-server"])) {
        $db = null;
        if (is_executable_pathenv(PHP_BINARY)) {
            $port = 8000;

            if (count($args) == 2) {
                if (array_key_exists("--port", $args) && !empty($args["--port"])) {
                    $port = $args["--port"];
                } else {
                    usage();
                }
            }

            echo "open in your browser:\n\n";
            echo "http://localhost:$port/client/index.html\n\n";
            chdir(__DIR__ . "/..");
            passthru(PHP_BINARY . " -S 0.0.0.0:$port");
        } else {
            echo "no php interpreter found in path\n";
        }
        exit(0);
    }

    try {
        mb_internal_encoding("UTF-8");
    } catch (Exception $e) {
        die("mbstring extension is not available\n");
    }

    if (!function_exists("curl_init")) {
        die("curl extension is not installed\n");
    }

    $required_backends = [
        "authentication",
        "authorization",
        "accounting",
        "users",
    ];

    try {
        if (PHP_VERSION_ID < 50600) {
            echo "minimal supported php version is 5.6\n";
            exit(1);
        }
    } catch (Exception $e) {
        echo "can't determine php version\n";
        exit(1);
    }

    try {
        $config = @json_decode(file_get_contents("config/config.json"), true);
    } catch (Exception $e) {
        $config = false;
    }

    if (!$config) {
        try {
            $config = @json_decode(json_encode(yaml_parse_file("config/config.yml")), true);
        } catch (Exception $e) {
            $config = false;
        }
    }

    if (!$config) {
        echo "config is empty\n";
        exit(1);
    }

    if (@!$config["backends"]) {
        echo "no backends defined\n";
        exit(1);
    }

    try {
        $db = new PDO_EXT(@$config["db"]["dsn"], @$config["db"]["username"], @$config["db"]["password"], @$config["db"]["options"]);
    } catch (Exception $e) {
        echo "can't open database " . $config["db"]["dsn"] . "\n";
        echo $e->getMessage() . "\n";
        exit(1);
    }

    try {
        $query = $db->query("select var_value from core_vars where var_name = 'dbVersion'", PDO::FETCH_ASSOC);
        if ($query) {
            $version = (int)($query->fetch()["var_value"]);
        } else {
            $version = 0;
        }
    } catch (\Exception $e) {
        $version = 0;
    }

    try {
        $redis = new Redis();
        $redis->connect($config["redis"]["host"], $config["redis"]["port"]);
        if (@$config["redis"]["password"]) {
            $redis->auth($config["redis"]["password"]);
        }
        $redis->setex("iAmOk", 1, "1");
    } catch (Exception $e) {
        echo "can't connect to redis server\n";
        exit(1);
    }

    $backends = [];
    foreach ($required_backends as $backend) {
        if (loadBackend($backend) === false) {
            die("can't load required backend [$backend]\n");
        }
    }

    if (count($args) && (strpos($argv[1], "--") === false || strpos($argv[1], "--") > 0)) {
        $backend = loadBackend($argv[1]);

        if (!$backend) {
            die("backend \"{$argv[1]}\" not found\n");
        }

        unset($args[$argv[1]]);

        if (!$backend->capabilities() || !array_key_exists("cli", $backend->capabilities())) {
            die("command line is not available for backend \"{$argv[1]}\"\n");
        }

        $backend->cli($args);

        exit(0);
    }

    if (count($args) == 1 && array_key_exists("--backends-with-cli", $args) && !isset($args["--backends-with-cli"])) {
        $cli = [];

        foreach ($config["backends"] as $b => $p) {
            $e = loadBackend($b);
            if ($e->capabilities() && array_key_exists("cli", $e->capabilities())) {
                $cli[] = $b;
            }
        }

        if (count($cli)) {
            echo "backends with cli support: " . implode(" ", $cli) . "\n";
        } else {
            echo "no backends with cli support found\n";
        }

        exit(0);
    }

    if (
        (count($args) == 1 && array_key_exists("--init-db", $args) && !isset($args["--init-db"]))
        ||
        (count($args) == 2 && array_key_exists("--init-db", $args) && !isset($args["--init-db"]) && array_key_exists("--skip", $args) && isset($args["--skip"]))
    ) {
        require_once "sql/install.php";
        require_once "utils/clear_cache.php";
        require_once "utils/reindex.php";

        initDB(@$args["--skip"]);
        startup();
        $n = clearCache(true);
        echo "$n cache entries cleared\n\n";
        reindex();
        echo "\n";
        exit(0);
    }

    startup();

    check_if_pid_exists();
    if (@$db) {
        $db->modify("delete from core_running_processes where done is null and coalesce(expire, 0) < " . time(), false, [ "silent" ]);

        $already = (int)$db->get("select count(*) as already from core_running_processes where done is null and params = :params and pid <> " . getmypid(), [
            'params' => $params,
        ], false, [ 'fieldlify', 'silent' ]);

        if ($already) {
            $script_result = "already running";
            exit(0);
        }
    }


    if (count($args) == 1 && array_key_exists("--cleanup", $args) && !isset($args["--cleanup"])) {
        require_once "utils/cleanup.php";
        cleanup();
        exit(0);
    }

    if (count($args) == 1 && array_key_exists("--init-mobile-issues-project", $args) && !isset($args["--init-mobile-issues-project"])) {
        require_once "utils/mobile_project.php";
        init_mp();
        exit(0);
    }

    if (count($args) == 1 && array_key_exists("--reindex", $args) && !isset($args["--reindex"])) {
        require_once "utils/reindex.php";
        require_once "utils/clear_cache.php";

        reindex();
        $n = clearCache(true);
        echo "$n cache entries cleared\n";
        exit(0);
    }

    if (count($args) == 1 && array_key_exists("--clear-cache", $args) && !isset($args["--clear-cache"])) {
        require_once "utils/clear_cache.php";

        $n = clearCache(true);
        echo "$n cache entries cleared\n";
        exit(0);
    }

    if (count($args) == 1 && array_key_exists("--admin-password", $args) && isset($args["--admin-password"])) {
        try {
            $db->exec("insert into core_users (uid, login, password) values (0, 'admin', 'admin')");
        } catch (Exception $e) {
            //
        }

        try {
            $sth = $db->prepare("update core_users set password = :password, login = 'admin', enabled = 1 where uid = 0");
            $sth->execute([ ":password" => password_hash($args["--admin-password"], PASSWORD_DEFAULT) ]);
            echo "admin account updated\n";
        } catch (Exception $e) {
            echo "admin account update failed\n";
        }
        exit(0);
    }

    if (count($args) == 1 && array_key_exists("--check-mail", $args) && isset($args["--check-mail"])) {
        $r = email($config, $args["--check-mail"], "test email", "test email");
        if ($r === true) {
            echo "email sended\n";
        } else
        if ($r === false) {
            echo "no email config found\n";
        } else {
            print_r($r);
        }
        exit(0);
    }

    if (count($args) == 1 && array_key_exists("--cron", $args)) {
        $parts = [ "minutely", "5min", "hourly", "daily", "monthly" ];
        $part = false;

        foreach ($parts as $p) {
            if (in_array($p, $args)) {
                $part = $p;
            }
        }

        if ($part) {
            foreach ($config["backends"] as $backend_name => $cfg) {
                $backend = loadBackend($backend_name);
                if ($backend) {
                    try {
                        if (!$backend->cron($part)) {
                            echo "$backend_name [$part] fail\n";
                        }
                    } catch (\Exception $e) {
                        echo "$backend_name [$part] exception\n";
                    }
                }
            }
        } else {
            usage();
        }

        exit(0);
    }

    // TODO: deprecated, dont forget to delete
    if ((count($args) == 1 || count($args) == 2) && array_key_exists("--autoconfigure-domophone", $args) && isset($args["--autoconfigure-domophone"])) {
        $domophone_id = $args["--autoconfigure-domophone"];

        $first_time = false;

        if (count($args) == 2) {
            if (array_key_exists("--first-time", $args)) {
                $first_time = true;
            } else {
                usage();
            }
        }

        if (checkInt($domophone_id)) {
            require_once "utils/autoconfigure_domophone.php";

            autoconfigure_domophone($domophone_id, $first_time);
            exit(0);
        } else {
            usage();
        }
    }

    if ((count($args) === 2 || count($args) === 3) && isset($args["--autoconfigure-device"], $args["--id"])) {
        $device_type = $args["--autoconfigure-device"];
        $device_id = $args["--id"];

        $first_time = false;

        if (count($args) === 3) {
            if (array_key_exists("--first-time", $args)) {
                $first_time = true;
            } else {
                usage();
            }
        }

        if (checkInt($device_id)) {
            require_once "utils/autoconfigure_device.php";

            autoconfigure_device($device_type, $device_id, $first_time);
            exit(0);
        }
    }

    if (count($args) == 1 && array_key_exists("--install-crontabs", $args) && !isset($args["--install-crontabs"])) {
        require_once "utils/install_crontabs.php";

        $n = installCrontabs();
        echo "$n crontabs lines added\n";
        exit(0);
    }

    if (count($args) == 1 && array_key_exists("--uninstall-crontabs", $args) && !isset($args["--uninstall-crontabs"])) {
        require_once "utils/install_crontabs.php";

        $n = unInstallCrontabs();
        echo "$n crontabs lines removed\n";
        exit(0);
    }

    if (count($args) == 1 && array_key_exists("--get-db-version", $args) && !isset($args["--get-db-version"])) {
        echo "dbVersion: $version\n";
        exit(0);
    }

    if (count($args) == 1 && array_key_exists("--check-backends", $args) && !isset($args["--check-backends"])) {
        $all_ok = true;

        foreach ($config["backends"] as $backend => $null) {
            $t = loadBackend($backend);
            if (!$t) {
                echo "loading $backend failed\n";
                $all_ok = false;
            } else {
                try {
                    if (!$t->check()) {
                        echo "error checking backend $backend\n";
                        $all_ok = false;
                    }
                } catch (\Exception $e) {
                    print_r($e);
                    $all_ok = false;
                }
            }
        }

        if ($all_ok) {
            echo "everything is all right\n";
        }
        exit(0);
    }

    if (count($args) == 1 && array_key_exists("--run-record-download", $args) && isset($args["--run-record-download"])) {
        $recordId = (int)$args["--run-record-download"];
        $dvr_exports = @loadBackend("dvr_exports");
        if ($dvr_exports && ($uuid = $dvr_exports->runDownloadRecordTask($recordId))) {
            $inbox = loadBackend("inbox");
            $files = loadBackend("files");

            $metadata = $files->getFileMetadata($uuid);

            $msgId = $inbox->sendMessage($metadata['subscriberId'], i18n("dvr.videoReady"), i18n("dvr.threeDays", $config['api']['mobile'], $uuid));
        }
        exit(0);
    }

    if (count($args) == 1 && array_key_exists("--write-yaml-config", $args) && !isset($args["--write-yaml-config"])) {
        file_put_contents("config/config.yml", yaml_emit($config));
        exit(0);
    }

    if (count($args) == 1 && array_key_exists("--write-json-config", $args) && !isset($args["--write-json-config"])) {
        file_put_contents("config/config.json", json_encode($config, JSON_PRETTY_PRINT));
        exit(0);
    }

    if (count($args) == 1 && array_key_exists("--print-config", $args) && !isset($args["--print-config"])) {
        print_r($config);
        exit(0);
    }

    usage();
