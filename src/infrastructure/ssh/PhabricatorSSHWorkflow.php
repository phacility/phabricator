<?php

abstract class PhabricatorSSHWorkflow extends PhutilArgumentWorkflow {

  private $user;
  private $iochannel;
  private $errorChannel;

  public function setErrorChannel(PhutilChannel $error_channel) {
    $this->errorChannel = $error_channel;
    return $this;
  }

  public function getErrorChannel() {
    return $this->errorChannel;
  }

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function getUser() {
    return $this->user;
  }

  final public function isExecutable() {
    return false;
  }

  public function setIOChannel(PhutilChannel $channel) {
    $this->iochannel = $channel;
    return $this;
  }

  public function getIOChannel() {
    return $this->iochannel;
  }

  public function readAllInput() {
    $channel = $this->getIOChannel();
    while ($channel->update()) {
      PhutilChannel::waitForAny(array($channel));
      if (!$channel->isOpenForReading()) {
        break;
      }
    }
    return $channel->read();
  }

  public function writeIO($data) {
    $this->getIOChannel()->write($data);
    return $this;
  }

  public function writeErrorIO($data) {
    $this->getErrorChannel()->write($data);
    return $this;
  }

  protected function newPassthruCommand() {
    return id(new PhabricatorSSHPassthruCommand())
      ->setErrorChannel($this->getErrorChannel());
  }

}
