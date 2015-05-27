<?php

final class PhabricatorIRCProtocolAdapter extends PhabricatorProtocolAdapter {

  private $socket;

  private $writeBuffer;
  private $readBuffer;

  private $nickIncrement = 0;

  public function getServiceType() {
    return 'IRC';
  }

  public function getServiceName() {
    return $this->getConfig('network', $this->getConfig('server'));
  }

  // Hash map of command translations
  public static $commandTranslations = array(
    'PRIVMSG' => 'MESSAGE',
  );

  public function connect() {
    $nick = $this->getConfig('nick', 'phabot');
    $server = $this->getConfig('server');
    $port = $this->getConfig('port', 6667);
    $pass = $this->getConfig('pass');
    $ssl = $this->getConfig('ssl', false);
    $user = $this->getConfig('user', $nick);

    if (!preg_match('/^[A-Za-z0-9_`[{}^|\]\\-]+$/', $nick)) {
      throw new Exception(
        pht(
          "Nickname '%s' is invalid!",
          $nick));
    }

    $errno = null;
    $error = null;
    if (!$ssl) {
      $socket = fsockopen($server, $port, $errno, $error);
    } else {
      $socket = fsockopen('ssl://'.$server, $port, $errno, $error);
    }
    if (!$socket) {
      throw new Exception(pht('Failed to connect, #%d: %s', $errno, $error));
    }
    $ok = stream_set_blocking($socket, false);
    if (!$ok) {
      throw new Exception(pht('Failed to set stream nonblocking.'));
    }

    $this->socket = $socket;
    if ($pass) {
      $this->write("PASS {$pass}");
    }
    $this->write("NICK {$nick}");
    $this->write("USER {$user} 0 * :{$user}");
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
      // We may have been interrupted by a signal, like a SIGINT. Try
      // selecting again. If the second select works, conclude that the failure
      // was most likely because we were signaled.
      $ok = @stream_select($read, $write, $except, $timeout_sec = 0);
      if ($ok === false) {
        throw new Exception(pht('%s failed!', 'stream_select()'));
      }
    }

    if ($read) {
      // Test for connection termination; in PHP, fread() off a nonblocking,
      // closed socket is empty string.
      if (feof($this->socket)) {
        // This indicates the connection was terminated on the other side,
        // just exit via exception and let the overseer restart us after a
        // delay so we can reconnect.
        throw new Exception(pht('Remote host closed connection.'));
      }
      do {
        $data = fread($this->socket, 4096);
        if ($data === false) {
          throw new Exception(pht('%s failed!', 'fread()'));
        } else {
          $messages[] = id(new PhabricatorBotMessage())
            ->setCommand('LOG')
            ->setBody('>>> '.$data);
          $this->readBuffer .= $data;
        }
      } while (strlen($data));
    }

    if ($write) {
      do {
        $len = fwrite($this->socket, $this->writeBuffer);
        if ($len === false) {
          throw new Exception(pht('%s failed!', 'fwrite()'));
        } else if ($len === 0) {
          break;
        } else {
          $messages[] = id(new PhabricatorBotMessage())
            ->setCommand('LOG')
            ->setBody('>>> '.substr($this->writeBuffer, 0, $len));
          $this->writeBuffer = substr($this->writeBuffer, $len);
        }
      } while (strlen($this->writeBuffer));
    }

    while (($m = $this->processReadBuffer()) !== false) {
      if ($m !== null) {
        $messages[] = $m;
      }
    }

    return $messages;
  }

  private function write($message) {
    $this->writeBuffer .= $message."\r\n";
    return $this;
  }

  public function writeMessage(PhabricatorBotMessage $message) {
    switch ($message->getCommand()) {
      case 'MESSAGE':
      case 'PASTE':
        $name = $message->getTarget()->getName();
        $body = $message->getBody();
        $this->write("PRIVMSG {$name} :{$body}");
        return true;
      default:
        return false;
    }
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

    if ($this->handleIRCProtocol($matches)) {
      return null;
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

  private function handleIRCProtocol(array $matches) {
    $data = $matches['data'];
    switch ($matches['command']) {
      case '433': // Nickname already in use
        // If we receive this error, try appending "-1", "-2", etc. to the nick
        $this->nickIncrement++;
        $nick = $this->getConfig('nick', 'phabot').'-'.$this->nickIncrement;
        $this->write("NICK {$nick}");
        return true;
      case '422': // Error - no MOTD
      case '376': // End of MOTD
        $nickpass = $this->getConfig('nickpass');
        if ($nickpass) {
          $this->write("PRIVMSG nickserv :IDENTIFY {$nickpass}");
        }
        $join = $this->getConfig('join');
        if (!$join) {
          throw new Exception(pht('Not configured to join any channels!'));
        }
        foreach ($join as $channel) {
          $this->write("JOIN {$channel}");
        }
        return true;
      case 'PING':
        $this->write("PONG {$data}");
        return true;
    }

    return false;
  }

  private function getBotCommand($irc_command) {
    if (isset(self::$commandTranslations[$irc_command])) {
      return self::$commandTranslations[$irc_command];
    }

    // We have no translation for this command, use as-is
    return $irc_command;
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
            rtrim($matches[2], "\r\n"),
          );
        }
        break;
    }

    // By default we assume there is no target, only a body
    return array(
      null,
      $data,
    );
  }

  public function disconnect() {
    // NOTE: FreeNode doesn't show quit messages if you've recently joined a
    // channel, presumably to prevent some kind of abuse. If you're testing
    // this, you may need to stay connected to the network for a few minutes
    // before it works. If you disconnect too quickly, the server will replace
    // your message with a "Client Quit" message.

    $quit = $this->getConfig('quit', pht('Shutting down.'));
    $this->write("QUIT :{$quit}");

    // Flush the write buffer.
    while (strlen($this->writeBuffer)) {
      $this->getNextMessages(0);
    }

    @fclose($this->socket);
    $this->socket = null;
  }
}
