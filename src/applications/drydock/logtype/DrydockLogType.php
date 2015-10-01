<?php

abstract class DrydockLogType extends Phobject {

  private $viewer;
  private $log;

  abstract public function getLogTypeName();
  abstract public function getLogTypeIcon(array $data);
  abstract public function renderLog(array $data);

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  final public function setLog(DrydockLog $log) {
    $this->log = $log;
    return $this;
  }

  final public function getLog() {
    return $this->log;
  }

  final public function getLogTypeConstant() {
    return $this->getPhobjectClassConstant('LOGCONST', 64);
  }

  final public static function getAllLogTypes() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getLogTypeConstant')
      ->execute();
  }

}
