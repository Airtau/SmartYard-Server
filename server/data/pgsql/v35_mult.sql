-- changa mobile subscribers
ALTER TABLE houses_subscribers_mobile
    DROP COLUMN auth_token,
    DROP COLUMN platform,
    DROP COLUMN push_token,
    DROP COLUMN push_token_type,
    DROP COLUMN voip_token,
    DROP COLUMN last_seen,
    DROP COLUMN voip_enabled;

-- flats <-> devices
CREATE TABLE IF NOT EXISTS houses_flats_devices
(
    houses_flat_device_id SERIAL NOT NULL PRIMARY KEY,
    house_flat_id integer not null,
    subscriber_device_id integer not null,
    voip_enabled integer                                                                                                -- ?
);
CREATE UNIQUE INDEX IF NOT EXISTS houses_flats_devices_uniq on houses_flats_devices(house_flat_id, subscriber_device_id);