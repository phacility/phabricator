<?php

final class AlmanacConduitUtil extends Phobject {

  public static function getHostPrivateKeyPath() {
    $root = dirname(phutil_get_library_root('phabricator'));
    $path = $root.'/conf/local/HOSTKEY';
    return $path;
  }

  public static function getHostIDPath() {
    $root = dirname(phutil_get_library_root('phabricator'));
    $path = $root.'/conf/local/HOSTID';
    return $path;
  }

}
