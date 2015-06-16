<?php

final class PhabricatorNotificationClient extends Phobject {

  const EXPECT_VERSION = 7;

  public static function getServerStatus() {
    $uri = PhabricatorEnv::getEnvConfig('notification.server-uri');
    $uri = id(new PhutilURI($uri))
      ->setPath('/status/')
      ->setQueryParam('instance', self::getInstance());

    // We always use HTTP to connect to the server itself: it's simpler and
    // there's no meaningful security benefit to securing this link today.
    // Force the protocol to HTTP in case users have set it to something else.
    $uri->setProtocol('http');

    list($body) = id(new HTTPSFuture($uri))
      ->setTimeout(3)
      ->resolvex();

    $status = phutil_json_decode($body);
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
      ->setPath('/')
      ->setQueryParam('instance', self::getInstance());

    id(new HTTPSFuture($server_uri, json_encode($data)))
      ->setMethod('POST')
      ->setTimeout(1)
      ->resolvex();
  }

  private static function getInstance() {
    $client_uri = PhabricatorEnv::getEnvConfig('notification.client-uri');
    return id(new PhutilURI($client_uri))->getPath();
  }

}
