<?php

abstract class PhabricatorSSHWorkflow
  extends PhutilArgumentWorkflow {

  // NOTE: We are explicitly extending "PhutilArgumentWorkflow", not
  // "PhabricatorManagementWorkflow". We want to avoid inheriting "getViewer()"
  // and other methods which assume workflows are administrative commands
  // like `bin/storage`.

  private $sshUser;
  private $iochannel;
  private $errorChannel;
  private $isClusterRequest;
  private $originalArguments;
  private $requestIdentifier;

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

  public function setSSHUser(PhabricatorUser $ssh_user) {
    $this->sshUser = $ssh_user;
    return $this;
  }

  public function getSSHUser() {
    return $this->sshUser;
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

  public function setRequestIdentifier($request_identifier) {
    $this->requestIdentifier = $request_identifier;
    return $this;
  }

  public function getRequestIdentifier() {
    return $this->requestIdentifier;
  }

  public function getSSHRemoteAddress() {
    $ssh_client = getenv('SSH_CLIENT');
    if (!strlen($ssh_client)) {
      return null;
    }

    // TODO: When commands are proxied, the original remote address should
    // also be proxied.

    // This has the format "<ip> <remote-port> <local-port>". Grab the IP.
    $remote_address = head(explode(' ', $ssh_client));

    try {
      $address = PhutilIPAddress::newAddress($remote_address);
    } catch (Exception $ex) {
      return null;
    }

    return $address->getAddress();
  }

}
