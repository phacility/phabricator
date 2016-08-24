<?php

/**
 * Simple IRC bot which runs as a Phabricator daemon. Although this bot is
 * somewhat useful, it is also intended to serve as a demo of how to write
 * "system agents" which communicate with Phabricator over Conduit, so you can
 * script system interactions and integrate with other systems.
 *
 * NOTE: This is super janky and experimental right now.
 */
final class PhabricatorBot extends PhabricatorDaemon {

  private $handlers;

  private $conduit;
  private $config;
  private $pollFrequency;
  private $protocolAdapter;

  protected function run() {
    $argv = $this->getArgv();
    if (count($argv) !== 1) {
      throw new Exception(
        pht(
          'Usage: %s %s',
          __CLASS__,
          '<json_config_file>'));
    }

    $json_raw = Filesystem::readFile($argv[0]);
    try {
      $config = phutil_json_decode($json_raw);
    } catch (PhutilJSONParserException $ex) {
      throw new PhutilProxyException(
        pht("File '%s' is not valid JSON!", $argv[0]),
        $ex);
    }

    $nick                   = idx($config, 'nick', 'phabot');
    $handlers               = idx($config, 'handlers', array());
    $protocol_adapter_class = idx(
      $config,
      'protocol-adapter',
      'PhabricatorIRCProtocolAdapter');
    $this->pollFrequency = idx($config, 'poll-frequency', 1);

    $this->config = $config;

    foreach ($handlers as $handler) {
      $obj = newv($handler, array($this));
      $this->handlers[] = $obj;
    }

    $ca_bundle = idx($config, 'https.cabundle');
    if ($ca_bundle) {
      HTTPSFuture::setGlobalCABundleFromPath($ca_bundle);
    }

    $conduit_uri = idx($config, 'conduit.uri');
    if ($conduit_uri) {
      $conduit_token = idx($config, 'conduit.token');

      // Normalize the path component of the URI so users can enter the
      // domain without the "/api/" part.
      $conduit_uri = new PhutilURI($conduit_uri);

      $conduit_host = (string)$conduit_uri->setPath('/');
      $conduit_uri = (string)$conduit_uri->setPath('/api/');

      $conduit = new ConduitClient($conduit_uri);
      if ($conduit_token) {
        $conduit->setConduitToken($conduit_token);
      } else {
        $conduit_user = idx($config, 'conduit.user');
        $conduit_cert = idx($config, 'conduit.cert');

        $response = $conduit->callMethodSynchronous(
          'conduit.connect',
          array(
            'client'            => __CLASS__,
            'clientVersion'     => '1.0',
            'clientDescription' => php_uname('n').':'.$nick,
            'host'              => $conduit_host,
            'user'              => $conduit_user,
            'certificate'       => $conduit_cert,
          ));
      }

      $this->conduit = $conduit;
    }

    // Instantiate Protocol Adapter, for now follow same technique as
    // handler instantiation
    $this->protocolAdapter = newv($protocol_adapter_class, array());
    $this->protocolAdapter
      ->setConfig($this->config)
      ->connect();

    $this->runLoop();

    $this->protocolAdapter->disconnect();
  }

  public function getConfig($key, $default = null) {
    return idx($this->config, $key, $default);
  }

  private function runLoop() {
    do {
      PhabricatorCaches::destroyRequestCache();

      $this->stillWorking();

      $messages = $this->protocolAdapter->getNextMessages($this->pollFrequency);
      if (count($messages) > 0) {
        foreach ($messages as $message) {
          $this->routeMessage($message);
        }
      }

      foreach ($this->handlers as $handler) {
        $handler->runBackgroundTasks();
      }
    } while (!$this->shouldExit());

  }

  public function writeMessage(PhabricatorBotMessage $message) {
    return $this->protocolAdapter->writeMessage($message);
  }

  private function routeMessage(PhabricatorBotMessage $message) {
    $ignore = $this->getConfig('ignore');
    if ($ignore) {
      $sender = $message->getSender();
      if ($sender && in_array($sender->getName(), $ignore)) {
        return;
      }
    }

    if ($message->getCommand() == 'LOG') {
      $this->log('[LOG] '.$message->getBody());
    }

    foreach ($this->handlers as $handler) {
      try {
        $handler->receiveMessage($message);
      } catch (Exception $ex) {
        phlog($ex);
      }
    }
  }

  public function getAdapter() {
    return $this->protocolAdapter;
  }

  public function getConduit() {
    if (empty($this->conduit)) {
      throw new Exception(
        pht(
          "This bot is not configured with a Conduit uplink. Set '%s' and ".
          "'%s' in the configuration to connect.",
          'conduit.uri',
          'conduit.token'));
    }
    return $this->conduit;
  }

}
