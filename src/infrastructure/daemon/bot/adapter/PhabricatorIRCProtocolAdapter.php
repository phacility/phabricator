<?php

final class PhabricatorIRCProtocolAdapter
  extends PhabricatorBaseProtocolAdapter {

  private $socket;

  private $writeBuffer;
  private $readBuffer;

  // Hash map of command translations
  public static $commandTranslations = array(
    'PRIVMSG' => 'MESSAGE');

  public function connect() {
    $nick = $this->getConfig('nick', 'phabot');
    $server = $this->getConfig('server');
    $port = $this->getConfig('port', 6667);
    $pass = $this->getConfig('pass');
    $ssl = $this->getConfig('ssl', false);
    $user = $this->getConfig('user', $nick);

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
    $this->writeMessage(
      id(new PhabricatorBotMessage())
      ->setCommand('USER')
      ->setBody("{$user} 0 * :{$user}"));
    if ($pass) {
      $this->writeMessage(
        id(new PhabricatorBotMessage())
        ->setCommand('PASS')
        ->setBody("{$pass}"));
    }

    $this->writeMessage(
      id(new PhabricatorBotMessage())
      ->setCommand('NICK')
      ->setBody("{$nick}"));
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
          $messages[] = id(new PhabricatorBotMessage())
            ->setCommand("LOG")
            ->setBody(">>> ".$data);
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
          $messages[] = id(new PhabricatorBotMessage())
            ->setCommand("LOG")
            ->setBody(">>> ".substr($this->writeBuffer, 0, $len));
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

  public function writeMessage(PhabricatorBotMessage $message) {
    $irc_command = $this->getIRCCommand($message->getCommand());
    switch ($message->getCommand()) {
    case 'MESSAGE':
      $data = $irc_command.' '.
        $message->getTarget()->getName().' :'.
        $message->getBody()."\r\n";
      break;
    default:
      $data = $irc_command.' '.
        $message->getBody()."\r\n";
      break;
    }

    return $this->write($data);
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
      '(?::(?P<sender>(\S+?))(?:!\S*)? )?'. // This may not be present.
      '(?P<command>[A-Z0-9]+) '.
      '(?P<data>.*)'.
      '$/';

    $matches = null;
    if (!preg_match($pattern, $message, $matches)) {
      throw new Exception("Unexpected message from server: {$message}");
    }

    $command = $this->getBotCommand($matches['command']);
    list($target, $body) = $this->parseMessageData($command, $matches['data']);

    if (!strlen($matches['sender'])) {
      $sender = null;
    } else {
      $sender = id(new PhabricatorBotUser())
       ->setName($matches['sender']);
    }

    $bot_message = id(new PhabricatorBotMessage())
      ->setSender($sender)
      ->setCommand($command)
      ->setTarget($target)
      ->setBody($body);

    return $bot_message;
  }

  private function getBotCommand($irc_command) {
    if (isset(self::$commandTranslations[$irc_command])) {
      return self::$commandTranslations[$irc_command];
    }

    // We have no translation for this command, use as-is
    return $irc_command;
  }

  private function getIRCCommand($original_bot_command) {
    foreach (self::$commandTranslations as $irc_command=>$bot_command) {
      if ($bot_command === $original_bot_command) {
        return $irc_command;
      }
    }

    return $original_bot_command;
  }

  private function parseMessageData($command, $data) {
    switch ($command) {
    case 'MESSAGE':
      $matches = null;
      if (preg_match('/^(\S+)\s+:?(.*)$/', $data, $matches)) {

        $target_name = $matches[1];
        if (strncmp($target_name, '#', 1) === 0) {
          $target = id(new PhabricatorBotChannel())
            ->setName($target_name);
        } else {
          $target = id(new PhabricatorBotUser())
            ->setName($target_name);
        }

        return array(
          $target,
          rtrim($matches[2], "\r\n"));
      }
      break;
    }

    // By default we assume there is no target, only a body
    return array(
      null,
      $data);
  }

  public function __destruct() {
    $this->write("QUIT Goodbye.\r\n");
    fclose($this->socket);
  }
}
