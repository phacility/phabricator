<?php

final class PhabricatorBotMessage {

  private $sender;
  private $command;
  private $body;
  private $target;
  private $public;

  public function __construct() {
    // By default messages are public
    $this->public = true;
  }

  public function setSender($sender) {
    $this->sender = $sender;
    return $this;
  }

  public function getSender() {
    return $this->sender;
  }

  public function setCommand($command) {
    $this->command = $command;
    return $this;
  }

  public function getCommand() {
    return $this->command;
  }

  public function setBody($body) {
    $this->body = $body;
    return $this;
  }

  public function getBody() {
    return $this->body;
  }

  public function setTarget($target) {
    $this->target = $target;
    return $this;
  }

  public function getTarget() {
    return $this->target;
  }

  public function isPublic() {
    return $this->public;
  }

  public function setPublic($is_public) {
    $this->public = $is_public;
    return $this;
  }

  public function getReplyTo() {
    if ($this->public) {
      return $this->target;
    } else {
      return $this->sender;
    }
  }
}
