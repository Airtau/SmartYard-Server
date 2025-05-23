<?php

    /**
     * @api {post} /mobile/frs/disLike "дизлайкнуть" (чужой, ложное срабатывание, разонравился)
     * @apiVersion 1.0.0
     * @apiDescription **в работе**
     *
     * для ленты событий указывать event (flat и face будут проигнорированы), для списка лиц указывать flat или flat и face
     *
     * @apiGroup FRS
     *
     * @apiHeader {String} authorization токен авторизации
     *
     * @apiBody {String} [event] идентификатор события (для ленты событий)
     * @apiBody {Number} [flatId] идентификатор квартиры (адрес) (для списка лиц)
     * @apiBody {Number} [faceId] идентификатор "лица" (для списка лиц)
     */

    use backends\plog\plog;
    use backends\frs\frs;

    auth();

    $plog = loadBackend("plog");
    if (!$plog) {
        response(422);
    }

    $frs = loadBackend("frs");
    if (!$frs) {
        response(422);
    }

    $event_uuid = @$postdata['event'];
    $face_id = null;
    $face_id2 = null;
    if ($event_uuid) {
        $event_data = $plog->getEventDetails($event_uuid);
        if (!$event_data) {
            response(404, false, i18n("mobile.404"));
        }
        $flat_id = (int)$event_data[plog::COLUMN_FLAT_ID];

        $face = json_decode($event_data[plog::COLUMN_FACE], false);
        if (isset($face->faceId) && $face->faceId > 0) {
            $face_id = (int)$face->faceId;
        }

        $face_id2 = $frs->getRegisteredFaceIdFrs($event_uuid);
        if ($face_id2 === false) {
            $face_id2 = null;
        }
    } else {
        $flat_id = @(int)$postdata['flatId'];
        $face_id = @(int)$postdata['faceId'];
    }

    if (($face_id === null || $face_id <= 0) && ($face_id2 === null || $face_id2 <= 0)) {
        response(403, false, i18n("mobile.404"));
    }

    $flat_ids = array_map(function($item) { return $item['flatId']; }, $subscriber['flats']);
    $f = in_array($flat_id, $flat_ids);
    if (!$f) {
        response(403, false, i18n("mobile.404"));
    }

    // TODO: check if FRS is allowed for flat_id

    $flat_owner = false;
    foreach ($subscriber['flats'] as $flat) {
        if ($flat['flatId'] == $flat_id) {
            $flat_owner = ($flat['role'] == 0);
            break;
        }
    }

    if ($flat_owner) {
        if ($face_id > 0) {
            $frs->detachFaceIdFromFlatFrs($face_id, $flat_id);
        }
        if ($face_id2 > 0) {
            $frs->detachFaceIdFromFlatFrs($face_id2, $flat_id);
        }
    } else {
        $subscriber_id = (int)$subscriber['subscriberId'];
        if ($face_id > 0) {
            $frs->detachFaceIdFrs($face_id, $subscriber_id);
        }
        if ($face_id2 > 0) {
            $frs->detachFaceIdFrs($face_id2, $subscriber_id);
        }
    }

    response();
