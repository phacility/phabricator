<?php

final class PhabricatorNotificationClient extends Phobject {

  public static function tryAnyConnection() {
    $servers = PhabricatorNotificationServerRef::getEnabledAdminServers();

    if (!$servers) {
      return;
    }

    foreach ($servers as $server) {
      $server->loadServerStatus();
      return;
    }

    return;
  }

  public static function tryToPostMessage(array $data) {
    $servers = PhabricatorNotificationServerRef::getEnabledAdminServers();

    shuffle($servers);

    foreach ($servers as $server) {
      try {
        $server->postMessage($data);
        return;
      } catch (Exception $ex) {
        // Just ignore any issues here.
      }
    }
  }

}
