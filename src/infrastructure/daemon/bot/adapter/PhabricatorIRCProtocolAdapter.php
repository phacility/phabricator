<?php

// TODO: Write PhabricatorBaseSocketProtocolAdapter
final class PhabricatorIRCProtocolAdapter
  extends PhabricatorBaseProtocolAdapter {

  private $socket;

  private $writeBuffer;
  private $readBuffer;

  public function connect() {
    $nick = idx($this->config, 'nick', 'phabot');
    $server = idx($this->config, 'server');
    $port = idx($this->config, 'port', 6667);
    $pass = idx($this->config, 'pass');
    $ssl = idx($this->config, 'ssl', false);
    $user = idx($this->config, 'user', $nick);

    if (!preg_match('/^[A-Za-z0-9_`[{}^|\]\\-]+$/', $nick)) {
      throw new Exception(
        "Nickname '{$nick}' is invalid!");
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
  }

  public function getNextMessages($poll_frequency) {
    $messages = array();

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
          $messages[] = new PhabricatorBotMessage(
            null,
            "LOG",
            "<<< ".$data
          );

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
          $messages[] = new PhabricatorBotMessage(
            null,
            "LOG",
            ">>> ".substr($this->writeBuffer, 0, $len));
          $this->writeBuffer = substr($this->writeBuffer, $len);
        }
      } while (strlen($this->writeBuffer));
    }

    while ($m = $this->processReadBuffer()) {
      $messages[] = $m;
    }

    return $messages;
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

    $irc_message = new PhabricatorBotMessage(
      idx($matches, 'sender'),
      $matches['command'],
      $matches['data']);

    return $irc_message;
  }

  public function __destruct() {
    $this->write("QUIT Goodbye.\r\n");
    fclose($this->socket);
  }

}
