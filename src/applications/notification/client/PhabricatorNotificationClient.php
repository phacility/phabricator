<?php

final class PhabricatorNotificationClient {

  const EXPECT_VERSION = 3;

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

  public static function postMessage(array $data) {
    $server_uri = PhabricatorEnv::getEnvConfig('notification.server-uri');

    id(new HTTPSFuture($server_uri, $data))
      ->setMethod('POST')
      ->setTimeout(1)
      ->resolvex();
  }

}
