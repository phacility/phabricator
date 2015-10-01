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
    $class = new ReflectionClass($this);

    $const = $class->getConstant('LOGCONST');
    if ($const === false) {
      throw new Exception(
        pht(
          '"%s" class "%s" must define a "%s" property.',
          __CLASS__,
          get_class($this),
          'LOGCONST'));
    }

    $limit = self::getLogTypeConstantByteLimit();
    if (!is_string($const) || (strlen($const) > $limit)) {
      throw new Exception(
        pht(
          '"%s" class "%s" has an invalid "%s" property. Field constants '.
          'must be strings and no more than %s bytes in length.',
          __CLASS__,
          get_class($this),
          'LOGCONST',
          new PhutilNumber($limit)));
    }

    return $const;
  }

  final private static function getLogTypeConstantByteLimit() {
    return 64;
  }

  final public static function getAllLogTypes() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getLogTypeConstant')
      ->execute();
  }

}
