<?php

final class PhabricatorStoragePatch extends Phobject {

  private $key;
  private $fullKey;
  private $name;
  private $type;
  private $after;
  private $legacy;
  private $dead;
  private $phase;

  const PHASE_DEFAULT = 'default';
  const PHASE_WORKER = 'worker';

  public function __construct(array $dict) {
    $this->key      = $dict['key'];
    $this->type     = $dict['type'];
    $this->fullKey  = $dict['fullKey'];
    $this->legacy   = $dict['legacy'];
    $this->name     = $dict['name'];
    $this->after    = $dict['after'];
    $this->dead     = $dict['dead'];
    $this->phase = $dict['phase'];
  }

  public function getLegacy() {
    return $this->legacy;
  }

  public function getAfter() {
    return $this->after;
  }

  public function getType() {
    return $this->type;
  }

  public function getName() {
    return $this->name;
  }

  public function getFullKey() {
    return $this->fullKey;
  }

  public function getKey() {
    return $this->key;
  }

  public function getPhase() {
    return $this->phase;
  }

  public function isDead() {
    return $this->dead;
  }

  public function getIsGlobalPatch() {
    return ($this->getType() == 'php');
  }

  public static function getPhaseList() {
    return array_keys(self::getPhaseMap());
  }

  public static function getDefaultPhase() {
    return self::PHASE_DEFAULT;
  }

  private static function getPhaseMap() {
    return array(
      self::PHASE_DEFAULT => array(
        'order' => 0,
      ),
      self::PHASE_WORKER => array(
        'order' => 1,
      ),
    );
  }

  public function newSortVector() {
    $map = self::getPhaseMap();
    $phase = $this->getPhase();

    return id(new PhutilSortVector())
      ->addInt($map[$phase]['order']);
  }

}
