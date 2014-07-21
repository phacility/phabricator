<?php

final class PhabricatorNotificationClient {

  const EXPECT_VERSION = 6;

  public static function getServerStatus() {
    $uri = PhabricatorEnv::getEnvConfig('notification.server-uri');
    $uri = new PhutilURI($uri);

    $uri->setPath('/status/');

    list($body) = id(new HTTPSFuture($uri))
      ->setTimeout(3)
      ->resolvex();

    $status = json_decode($body, true);
    if (!is_array($status)) {
      throw new Exception(
        pht(
          'Expected JSON response from notification server, received: %s',
          $body));
    }

    return $status;
  }

  public static function tryToPostMessage(array $data) {
    if (!PhabricatorEnv::getEnvConfig('notification.enabled')) {
      return;
    }

    try {
      self::postMessage($data);
    } catch (Exception $ex) {
      // Just ignore any issues here.
      phlog($ex);
    }
  }

  private static function postMessage(array $data) {
    $server_uri = PhabricatorEnv::getEnvConfig('notification.server-uri');
    $server_uri = id(new PhutilURI($server_uri))
      ->setPath('/');

    id(new HTTPSFuture($server_uri, json_encode($data)))
      ->setMethod('POST')
      ->setTimeout(1)
      ->resolvex();
  }

}
