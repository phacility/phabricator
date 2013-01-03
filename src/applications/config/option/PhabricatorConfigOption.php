<?php

final class PhabricatorConfigOption
  extends Phobject
  implements PhabricatorMarkupInterface{

  private $key;
  private $default;
  private $summary;
  private $description;
  private $type;
  private $options;
  private $group;
  private $examples;
  private $locked;
  private $hidden;
  private $masked;
  private $baseClass;

  public function setBaseClass($base_class) {
    $this->baseClass = $base_class;
    return $this;
  }

  public function getBaseClass() {
    return $this->baseClass;
  }

  public function setMasked($masked) {
    $this->masked = $masked;
    return $this;
  }

  public function getMasked() {
    if ($this->getHidden()) {
      return true;
    }
    return $this->masked;
  }

  public function setHidden($hidden) {
    $this->hidden = $hidden;
    return $this;
  }

  public function getHidden() {
    return $this->hidden;
  }

  public function setLocked($locked) {
    $this->locked = $locked;
    return $this;
  }

  public function getLocked() {
    if ($this->getHidden()) {
      return true;
    }
    return $this->locked;
  }

  public function addExample($value, $description) {
    $this->examples[] = array($value, $description);
    return $this;
  }

  public function getExamples() {
    return $this->examples;
  }

  public function setGroup(PhabricatorApplicationConfigOptions $group) {
    $this->group = $group;
    return $this;
  }

  public function getGroup() {
    return $this->group;
  }

  public function setOptions(array $options) {
    $this->options = $options;
    return $this;
  }

  public function getOptions() {
    return $this->options;
  }

  public function setKey($key) {
    $this->key = $key;
    return $this;
  }

  public function getKey() {
    return $this->key;
  }

  public function setDefault($default) {
    $this->default = $default;
    return $this;
  }

  public function getDefault() {
    return $this->default;
  }

  public function setSummary($summary) {
    $this->summary = $summary;
    return $this;
  }

  public function getSummary() {
    if (empty($this->summary)) {
      return $this->getDescription();
    }
    return $this->summary;
  }

  public function setDescription($description) {
    $this->description = $description;
    return $this;
  }

  public function getDescription() {
    return $this->description;
  }

  public function setType($type) {
    $this->type = $type;
    return $this;
  }

  public function getType() {
    return $this->type;
  }

/* -(  PhabricatorMarkupInterface  )----------------------------------------- */

  public function getMarkupFieldKey($field) {
    return $this->getKey().':'.$field;
  }

  public function newMarkupEngine($field) {
    return PhabricatorMarkupEngine::newMarkupEngine(array());
  }

  public function getMarkupText($field) {
    return $this->getDescription();
  }

  public function didMarkupText($field, $output, PhutilMarkupEngine $engine) {
    return $output;
  }

  public function shouldUseMarkupCache($field) {
    return false;
  }

}
