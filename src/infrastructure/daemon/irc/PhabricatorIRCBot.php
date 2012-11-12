<?php

/**
 * Simple IRC bot which runs as a Phabricator daemon. Although this bot is
 * somewhat useful, it is also intended to serve as a demo of how to write
 * "system agents" which communicate with Phabricator over Conduit, so you can
 * script system interactions and integrate with other systems.
 *
 * NOTE: This is super janky and experimental right now.
 *
 * @group irc
 */
final class PhabricatorIRCBot extends PhabricatorDaemon {

  private $socket;
  private $handlers;

  private $writeBuffer;
  private $readBuffer;

  private $conduit;
  private $config;

  public function run() {

    $argv = $this->getArgv();
    if (count($argv) !== 1) {
      throw new Exception("usage: PhabricatorIRCBot <json_config_file>");
    }

    $json_raw = Filesystem::readFile($argv[0]);
    $config = json_decode($json_raw, true);
    if (!is_array($config)) {
      throw new Exception("File '{$argv[0]}' is not valid JSON!");
    }

    $server   = idx($config, 'server');
    $port     = idx($config, 'port', 6667);
    $handlers = idx($config, 'handlers', array());
    $pass     = idx($config, 'pass');
    $nick     = idx($config, 'nick', 'phabot');
    $user     = idx($config, 'user', $nick);
    $ssl      = idx($config, 'ssl', false);
    $nickpass = idx($config, 'nickpass');

    $this->config = $config;

    if (!preg_match('/^[A-Za-z0-9_`[{}^|\]\\-]+$/', $nick)) {
      throw new Exception(
        "Nickname '{$nick}' is invalid!");
    }

    foreach ($handlers as $handler) {
      $obj = newv($handler, array($this));
      $this->handlers[] = $obj;
    }

    $conduit_uri = idx($config, 'conduit.uri');
    if ($conduit_uri) {
      $conduit_user = idx($config, 'conduit.user');
      $conduit_cert = idx($config, 'conduit.cert');

      // Normalize the path component of the URI so users can enter the
      // domain without the "/api/" part.
      $conduit_uri = new PhutilURI($conduit_uri);
      $conduit_uri->setPath('/api/');
      $conduit_uri = (string)$conduit_uri;

      $conduit = new ConduitClient($conduit_uri);
      $response = $conduit->callMethodSynchronous(
        'conduit.connect',
        array(
          'client'            => 'PhabricatorIRCBot',
          'clientVersion'     => '1.0',
          'clientDescription' => php_uname('n').':'.$nick,
          'user'              => $conduit_user,
          'certificate'       => $conduit_cert,
        ));

      $this->conduit = $conduit;
    }

    $errno = null;
    $error = null;
    if (!$ssl) {
      $socket = fsockopen($server, $port, $errno, $error);
    } else {
      $socket = fsockopen('ssl://'.$server, $port, $errno, $error);
    }
    if (!$socket) {
      throw new Exception("Failed to connect, #{$errno}: {$error}");
    }
    $ok = stream_set_blocking($socket, false);
    if (!$ok) {
      throw new Exception("Failed to set stream nonblocking.");
    }

    $this->socket = $socket;
    $this->writeCommand('USER', "{$user} 0 * :{$user}");
    if ($pass) {
      $this->writeCommand('PASS', "{$pass}");
    }

    $this->writeCommand('NICK', "{$nick}");
    $this->runSelectLoop();
  }

  public function getConfig($key, $default = null) {
    return idx($this->config, $key, $default);
  }

  private function runSelectLoop() {
    do {
      $this->stillWorking();

      $read = array($this->socket);
      if (strlen($this->writeBuffer)) {
        $write = array($this->socket);
      } else {
        $write = array();
      }
      $except = array();

      $ok = @stream_select($read, $write, $except, $timeout_sec = 1);
      if ($ok === false) {
        throw new Exception(
          "socket_select() failed: ".socket_strerror(socket_last_error()));
      }

      if ($read) {
        // Test for connection termination; in PHP, fread() off a nonblocking,
        // closed socket is empty string.
        if (feof($this->socket)) {
          // This indicates the connection was terminated on the other side,
          // just exit via exception and let the overseer restart us after a
          // delay so we can reconnect.
          throw new Exception("Remote host closed connection.");
        }
        do {
          $data = fread($this->socket, 4096);
          if ($data === false) {
            throw new Exception("fread() failed!");
          } else {
            $this->debugLog(true, $data);
            $this->readBuffer .= $data;
          }
        } while (strlen($data));
      }

      if ($write) {
        do {
          $len = fwrite($this->socket, $this->writeBuffer);
          if ($len === false) {
            throw new Exception("fwrite() failed!");
          } else {
            $this->debugLog(false, substr($this->writeBuffer, 0, $len));
            $this->writeBuffer = substr($this->writeBuffer, $len);
          }
        } while (strlen($this->writeBuffer));
      }

      do {
        $routed_message = $this->processReadBuffer();
      } while ($routed_message);

      foreach ($this->handlers as $handler) {
        $handler->runBackgroundTasks();
      }

    } while (true);
  }

  private function write($message) {
    $this->writeBuffer .= $message;
    return $this;
  }

  public function writeCommand($command, $message) {
    return $this->write($command.' '.$message."\r\n");
  }

  private function processReadBuffer() {
    $until = strpos($this->readBuffer, "\r\n");
    if ($until === false) {
      return false;
    }

    $message = substr($this->readBuffer, 0, $until);
    $this->readBuffer = substr($this->readBuffer, $until + 2);

    $pattern =
      '/^'.
      '(?:(?P<sender>:(\S+)) )?'. // This may not be present.
      '(?P<command>[A-Z0-9]+) '.
      '(?P<data>.*)'.
      '$/';

    $matches = null;
    if (!preg_match($pattern, $message, $matches)) {
      throw new Exception("Unexpected message from server: {$message}");
    }

    $irc_message = new PhabricatorIRCMessage(
      idx($matches, 'sender'),
      $matches['command'],
      $matches['data']);

    $this->routeMessage($irc_message);

    return true;
  }

  private function routeMessage(PhabricatorIRCMessage $message) {
    foreach ($this->handlers as $handler) {
      try {
        $handler->receiveMessage($message);
      } catch (Exception $ex) {
        phlog($ex);
      }
    }
  }

  public function __destruct() {
    $this->write("QUIT Goodbye.\r\n");
    fclose($this->socket);
  }

  private function debugLog($is_read, $message) {
    if ($this->getTraceMode()) {
      echo $is_read ? '<<< ' : '>>> ';
      echo addcslashes($message, "\0..\37\177..\377");
      echo "\n";
    }
  }

  public function getConduit() {
    if (empty($this->conduit)) {
      throw new Exception(
        "This bot is not configured with a Conduit uplink. Set 'conduit.uri', ".
        "'conduit.user' and 'conduit.cert' in the configuration to connect.");
    }
    return $this->conduit;
  }

}
