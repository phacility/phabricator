<?php

abstract class DiffusionGitWireProtocol extends Phobject {

  private $protocolLog;

  final public function setProtocolLog(PhabricatorProtocolLog $protocol_log) {
    $this->protocolLog = $protocol_log;
    return $this;
  }

  final public function getProtocolLog() {
    return $this->protocolLog;
  }

  abstract public function willReadBytes($bytes);
  abstract public function willWriteBytes($bytes);

}
