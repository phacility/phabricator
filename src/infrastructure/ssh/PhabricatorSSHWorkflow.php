<?php

abstract class PhabricatorSSHWorkflow extends PhabricatorManagementWorkflow {

  private $user;
  private $iochannel;
  private $errorChannel;
  private $isClusterRequest;
  private $originalArguments;

  public function isExecutable() {
    return false;
  }

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

  public function setIsClusterRequest($is_cluster_request) {
    $this->isClusterRequest = $is_cluster_request;
    return $this;
  }

  public function getIsClusterRequest() {
    return $this->isClusterRequest;
  }

  public function setOriginalArguments(array $original_arguments) {
    $this->originalArguments = $original_arguments;
    return $this;
  }

  public function getOriginalArguments() {
    return $this->originalArguments;
  }

}
