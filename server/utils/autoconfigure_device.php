<?php

require_once __DIR__ . '/SmartConfigurator/autoload.php';
require_once __DIR__ . '/array_diff_assoc_recursive.php';

use utils\SmartConfigurator\DbConfigCollector\{CameraDbConfigCollector, DomophoneDbConfigCollector};
use utils\SmartConfigurator\SmartConfigurator;

function autoconfigure_device(string $deviceType, int $deviceId, bool $firstTime = false)
{
    global $config;

    $householdsBackend = loadBackend('households');

    switch ($deviceType) {
        case 'domophone':
            $deviceData = $householdsBackend->getDomophone($deviceId);

            if (!$deviceData) {
                throw new Error("Device '$deviceType' with ID $deviceId not found in the DB");
            }

            if (!$deviceData['enabled']) {
                echo 'Device is disabled' . PHP_EOL;
                exit(0);
            }

            $dbConfigCollector = new DomophoneDbConfigCollector($config, $deviceData, $householdsBackend);
            break;

        case 'camera':
            $camerasBackend = loadBackend('cameras');
            $deviceData = $camerasBackend->getCamera($deviceId);

            if (!$deviceData) {
                throw new Error("Device '$deviceType' with ID $deviceId not found in the DB");
            }

            if (!$deviceData['enabled']) {
                echo 'Device is disabled' . PHP_EOL;
                exit(0);
            }

            $dbConfigCollector = new CameraDbConfigCollector($config, $deviceData);
            break;

        default:
            throw new ValueError("Unsupported device type '$deviceType'");
    }

    try {
        $device = loadDevice(
            $deviceType,
            $deviceData['model'],
            $deviceData['url'],
            $deviceData['credentials'],
            $firstTime
        );

        if ($device) {
            $configurator = new SmartConfigurator($device, $dbConfigCollector);
            $configurator->makeConfiguration();
            $householdsBackend->autoconfigDone($deviceId);
        }
    } catch (Exception $e) {
        throw new Error($e->getMessage());
    }
}
