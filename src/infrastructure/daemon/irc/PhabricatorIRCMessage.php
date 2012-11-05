<?php

final class PhabricatorIRCMessage {

  private $sender;
  private $command;
  private $data;

  public function __construct($sender, $command, $data) {
    $this->sender = $sender;
    $this->command = $command;
    $this->data = $data;
  }

  public function getRawSender() {
    return $this->sender;
  }

  public function getRawData() {
    return $this->data;
  }

  public function getCommand() {
    return $this->command;
  }

  public function getReplyTo() {
    switch ($this->getCommand()) {
      case 'PRIVMSG':
        $target = $this->getTarget();
        if ($target[0] == '#') {
          return $target;
        }

        $matches = null;
        if (preg_match('/^:([^!]+)!/', $this->sender, $matches)) {
          return $matches[1];
        }
        break;
    }
    return null;
  }

  public function getSenderNickname() {
    $nick = $this->getRawSender();
    $nick = ltrim($nick, ':');
    $nick = head(explode('!', $nick));
    return $nick;
  }

  public function getTarget() {
    switch ($this->getCommand()) {
      case 'PRIVMSG':
        $matches = null;
        $raw = $this->getRawData();
        if (preg_match('/^(\S+)\s/', $raw, $matches)) {
          return $matches[1];
        }
       break;
    }
    return null;
  }

  public function getMessageText() {
    switch ($this->getCommand()) {
      case 'PRIVMSG':
        $matches = null;
        $raw = $this->getRawData();
        if (preg_match('/^\S+\s+:?(.*)$/', $raw, $matches)) {
          return rtrim($matches[1], "\r\n");
        }
        break;
    }
    return null;
  }

}
