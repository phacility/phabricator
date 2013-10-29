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

  public function passthruIO(ExecFuture $future) {
    $exec_channel = new PhutilExecChannel($future);
    $exec_channel->setStderrHandler(array($this, 'writeErrorIOCallback'));

    $io_channel = $this->getIOChannel();
    $error_channel = $this->getErrorChannel();

    $channels = array($exec_channel, $io_channel, $error_channel);

    while (true) {
      PhutilChannel::waitForAny($channels);

      $io_channel->update();
      $exec_channel->update();
      $error_channel->update();

      $done = !$exec_channel->isOpen();

      $data = $io_channel->read();
      if (strlen($data)) {
        $exec_channel->write($data);
      }

      $data = $exec_channel->read();
      if (strlen($data)) {
        $io_channel->write($data);
      }

      // If we have nothing left on stdin, close stdin on the subprocess.
      if (!$io_channel->isOpenForReading()) {
        // TODO: This should probably be part of PhutilExecChannel?
        $future->write('');
      }

      if ($done) {
        break;
      }
    }

    list($err) = $future->resolve();

    return $err;
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

  public function writeErrorIOCallback(PhutilChannel $channel, $data) {
    $this->writeErrorIO($data);
  }

}
