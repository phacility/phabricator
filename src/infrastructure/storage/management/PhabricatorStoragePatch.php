<?php

final class PhabricatorStoragePatch extends Phobject {

  private $key;
  private $fullKey;
  private $name;
  private $type;
  private $after;
  private $legacy;
  private $dead;

  public function __construct(array $dict) {
    $this->key      = $dict['key'];
    $this->type     = $dict['type'];
    $this->fullKey  = $dict['fullKey'];
    $this->legacy   = $dict['legacy'];
    $this->name     = $dict['name'];
    $this->after    = $dict['after'];
    $this->dead     = $dict['dead'];
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

  public function isDead() {
    return $this->dead;
  }

}
