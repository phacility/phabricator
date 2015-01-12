<?php

final class AlmanacKeys extends Phobject {

  public static function getKeyPath($key_name) {
    $root = dirname(phutil_get_library_root('phabricator'));
    $keys = $root.'/conf/keys/';

    return $keys.ltrim($key_name, '/');
  }

}
