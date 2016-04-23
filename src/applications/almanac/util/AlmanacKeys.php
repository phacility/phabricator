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

  public static function getLiveDevice() {
    $device_id = self::getDeviceID();
    if (!$device_id) {
      return null;
    }

    $cache = PhabricatorCaches::getRequestCache();
    $cache_key = 'almanac.device.self';

    $device = $cache->getKey($cache_key);
    if (!$device) {
      $viewer = PhabricatorUser::getOmnipotentUser();
      $device = id(new AlmanacDeviceQuery())
        ->setViewer($viewer)
        ->withNames(array($device_id))
        ->executeOne();
      if (!$device) {
        throw new Exception(
          pht(
            'This host has device ID "%s", but there is no corresponding '.
            'device record in Almanac.',
            $device_id));
      }
      $cache->setKey($cache_key, $device);
    }

    return $device;
  }

  public static function getClusterSSHUser() {
    // NOTE: When instancing, we currently use the SSH username to figure out
    // which instance you are connecting to. We can't use the host name because
    // we have no way to tell which host you think you're reaching: the SSH
    // protocol does not have a mechanism like a "Host" header.
    $username = PhabricatorEnv::getEnvConfig('cluster.instance');
    if (strlen($username)) {
      return $username;
    }

    $username = PhabricatorEnv::getEnvConfig('diffusion.ssh-user');
    if (strlen($username)) {
      return $username;
    }

    return null;
  }

}
