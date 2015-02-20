<?php

final class AlmanacKeys extends Phobject {

  public static function getKeyPath($key_name) {
    $root = dirname(phutil_get_library_root('phabricator'));
    $keys = $root.'/conf/keys/';

    return $keys.ltrim($key_name, '/');
  }

  public static function getDeviceID() {
    $device_id_path = self::getKeyPath('device.id');

    if (Filesystem::pathExists($device_id_path)) {
      return trim(Filesystem::readFile($device_id_path));
    }

    return null;
  }

}
