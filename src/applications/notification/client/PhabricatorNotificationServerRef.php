<?php

final class PhabricatorNotificationServerRef
  extends Phobject {

  private $type;
  private $host;
  private $port;
  private $protocol;
  private $path;
  private $isDisabled;

  const KEY_REFS = 'notification.refs';

  public function setType($type) {
    $this->type = $type;
    return $this;
  }

  public function getType() {
    return $this->type;
  }

  public function setHost($host) {
    $this->host = $host;
    return $this;
  }

  public function getHost() {
    return $this->host;
  }

  public function setPort($port) {
    $this->port = $port;
    return $this;
  }

  public function getPort() {
    return $this->port;
  }

  public function setProtocol($protocol) {
    $this->protocol = $protocol;
    return $this;
  }

  public function getProtocol() {
    return $this->protocol;
  }

  public function setPath($path) {
    $this->path = $path;
    return $this;
  }

  public function getPath() {
    return $this->path;
  }

  public function setIsDisabled($is_disabled) {
    $this->isDisabled = $is_disabled;
    return $this;
  }

  public function getIsDisabled() {
    return $this->isDisabled;
  }

  public static function getLiveServers() {
    $cache = PhabricatorCaches::getRequestCache();

    $refs = $cache->getKey(self::KEY_REFS);
    if (!$refs) {
      $refs = self::newRefs();
      $cache->setKey(self::KEY_REFS, $refs);
    }

    return $refs;
  }

  public static function newRefs() {
    $configs = PhabricatorEnv::getEnvConfig('notification.servers');

    $refs = array();
    foreach ($configs as $config) {
      $ref = id(new self())
        ->setType($config['type'])
        ->setHost($config['host'])
        ->setPort($config['port'])
        ->setProtocol($config['protocol'])
        ->setPath(idx($config, 'path'))
        ->setIsDisabled(idx($config, 'disabled', false));
      $refs[] = $ref;
    }

    return $refs;
  }

  public static function getEnabledServers() {
    $servers = self::getLiveServers();

    foreach ($servers as $key => $server) {
      if ($server->getIsDisabled()) {
        unset($servers[$key]);
      }
    }

    return array_values($servers);
  }

  public static function getEnabledAdminServers() {
    $servers = self::getEnabledServers();

    foreach ($servers as $key => $server) {
      if (!$server->isAdminServer()) {
        unset($servers[$key]);
      }
    }

    return array_values($servers);
  }

  public static function getEnabledClientServers($with_protocol) {
    $servers = self::getEnabledServers();

    foreach ($servers as $key => $server) {
      if ($server->isAdminServer()) {
        unset($servers[$key]);
        continue;
      }

      $protocol = $server->getProtocol();
      if ($protocol != $with_protocol) {
        unset($servers[$key]);
        continue;
      }
    }

    return array_values($servers);
  }

  public function isAdminServer() {
    return ($this->type == 'admin');
  }

  public function getURI($to_path = null) {
    if ($to_path === null || !strlen($to_path)) {
      $to_path = '';
    } else {
      $to_path = ltrim($to_path, '/');
    }

    $base_path = $this->getPath();
    if ($base_path === null || !strlen($base_path)) {
      $base_path = '';
    } else {
      $base_path = rtrim($base_path, '/');
    }
    $full_path = $base_path.'/'.$to_path;

    $uri = id(new PhutilURI('http://'.$this->getHost()))
      ->setProtocol($this->getProtocol())
      ->setPort($this->getPort())
      ->setPath($full_path);

    $instance = PhabricatorEnv::getEnvConfig('cluster.instance');
    if ($instance !== null && strlen($instance)) {
      $uri->replaceQueryParam('instance', $instance);
    }

    return $uri;
  }

  public function getWebsocketURI($to_path = null) {
    $instance = PhabricatorEnv::getEnvConfig('cluster.instance');
    if ($instance !== null && strlen($instance)) {
      $to_path = $to_path.'~'.$instance.'/';
    }

    $uri = $this->getURI($to_path);

    if ($this->getProtocol() == 'https') {
      $uri->setProtocol('wss');
    } else {
      $uri->setProtocol('ws');
    }

    return $uri;
  }

  public function testClient() {
    if ($this->isAdminServer()) {
      throw new Exception(
        pht('Unable to test client on an admin server!'));
    }

    $server_uri = $this->getURI();

    try {
      id(new HTTPSFuture($server_uri))
        ->setTimeout(2)
        ->resolvex();
    } catch (HTTPFutureHTTPResponseStatus $ex) {
      // This is what we expect when things are working correctly.
      if ($ex->getStatusCode() == 501) {
        return true;
      }
      throw $ex;
    }

    throw new Exception(
      pht('Got HTTP 200, but expected HTTP 501 (WebSocket Upgrade)!'));
  }

  public function loadServerStatus() {
    if (!$this->isAdminServer()) {
      throw new Exception(
        pht(
          'Unable to load server status: this is not an admin server!'));
    }

    $server_uri = $this->getURI('/status/');

    list($body) = $this->newFuture($server_uri)
      ->resolvex();

    return phutil_json_decode($body);
  }

  public function postMessage(array $data) {
    if (!$this->isAdminServer()) {
      throw new Exception(
        pht('Unable to post message: this is not an admin server!'));
    }

    $server_uri = $this->getURI('/');
    $payload = phutil_json_encode($data);

    $this->newFuture($server_uri, $payload)
      ->setMethod('POST')
      ->resolvex();
  }

  private function newFuture($uri, $data = null) {
    if ($data === null) {
      $future = new HTTPSFuture($uri);
    } else {
      $future = new HTTPSFuture($uri, $data);
    }

    $future->setTimeout(2);

    // At one point, a HackerOne researcher reported a "Location:" redirect
    // attack here (if the attacker can gain control of the notification
    // server or the configuration).

    // Although this attack is not particularly concerning, we don't expect
    // Aphlict to ever issue a "Location:" header, so receiving one indicates
    // something is wrong and declining to follow the header may make debugging
    // easier.

    $future->setFollowLocation(false);

    return $future;
  }

}
