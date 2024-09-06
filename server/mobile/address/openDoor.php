<?php

/**
 * @api {post} /mobile/address/openDoor открыть дверь (калитку, ворота, шлагбаум)
 * @apiVersion 1.0.0
 * @apiDescription ***нуждается в доработке***
 *
 * @apiGroup Address
 *
 * @apiHeader {String} authorization токен авторизации
 *
 * @apiParam {Number} domophoneId идентификатор домофона
 * @apiParam {Number=0,1,2} [doorId=0] идентификатор двери (калитки, ворот, шлагбаума)
 *
 * @apiErrorExample Ошибки
 * 403 требуется авторизация
 * 422 неверный формат данных
 * 404 пользователь не найден
 * 410 авторизация отозвана
 * 424 неверный токен
 */

    auth();

    $domophone_id = (int)@$postdata['domophoneId'];
    $door_id = (int)@$postdata['doorId'];
    $households = loadBackend("households");

    // Check intercom is blocking
    $blocked = true;
    foreach($subscriber['flats'] as $flat) {
        $flatDetail = $households->getFlat($flat['flatId']);
        if ($flatDetail['autoBlock'] || $flatDetail['adminBlock']) {
            continue;
        }

        foreach ($flatDetail['entrances'] as $entrance) {
            $domophoneId = intval($entrance['domophoneId']);
            $e = $households->getEntrance($entrance['entranceId']);
            $doorId = intval($e['domophoneOutput']);
            if($domophone_id == $domophoneId && $door_id == $doorId && !$flatDetail['manualBlock'] ) {
                $blocked = false;
                break;
            }
        }

        if ($blocked == false) {
            break;
        }
    }

    if (!$blocked) {
        $households = loadBackend("households");
        $domophone = $households->getDomophone($domophone_id);

        try {
            $model = loadDevice('domophone', $domophone["model"], $domophone["url"], $domophone["credentials"]);
            $model->openLock($door_id);
            $plog = loadBackend("plog");
            if ($plog) {
                $plog->addDoorOpenDataById(time(), $domophone_id, $plog::EVENT_OPENED_BY_APP, $door_id, $subscriber['mobile']);
            }
        }
        catch (\Exception $e) {
            response(404, false, i18n("mobile.error"), i18n("mobile.unavailable"));
        }
        response();
    } else {
        response(404, false, i18n("mobile.404"), i18n("mobile.serviceUnavailable"));
    }