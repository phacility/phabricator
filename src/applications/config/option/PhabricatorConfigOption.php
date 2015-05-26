<?php

final class PhabricatorConfigOption
  extends Phobject
  implements PhabricatorMarkupInterface {

  private $key;
  private $default;
  private $summary;
  private $description;
  private $type;
  private $boolOptions;
  private $enumOptions;
  private $group;
  private $examples;
  private $locked;
  private $lockedMessage;
  private $hidden;
  private $baseClass;
  private $customData;
  private $customObject;

  public function setBaseClass($base_class) {
    $this->baseClass = $base_class;
    return $this;
  }

  public function getBaseClass() {
    return $this->baseClass;
  }

  public function setHidden($hidden) {
    $this->hidden = $hidden;
    return $this;
  }

  public function getHidden() {
    if ($this->hidden) {
      return true;
    }

    return idx(
      PhabricatorEnv::getEnvConfig('config.hide'),
      $this->getKey(),
      false);
  }

  public function setLocked($locked) {
    $this->locked = $locked;
    return $this;
  }

  public function getLocked() {
    if ($this->locked) {
      return true;
    }

    if ($this->getHidden()) {
      return true;
    }

    return idx(
      PhabricatorEnv::getEnvConfig('config.lock'),
      $this->getKey(),
      false);
  }

  public function setLockedMessage($message) {
    $this->lockedMessage = $message;
    return $this;
  }

  public function getLockedMessage() {
    if ($this->lockedMessage !== null) {
      return $this->lockedMessage;
    }
    return pht(
      'This configuration is locked and can not be edited from the web '.
      'interface. Use `%s` in `%s` to edit it.',
      './bin/config',
      'phabricator/');
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

  public function setBoolOptions(array $options) {
    $this->boolOptions = $options;
    return $this;
  }

  public function getBoolOptions() {
    if ($this->boolOptions) {
      return $this->boolOptions;
    }
    return array(
      pht('True'),
      pht('False'),
    );
  }

  public function setEnumOptions(array $options) {
    $this->enumOptions = $options;
    return $this;
  }

  public function getEnumOptions() {
    if ($this->enumOptions) {
      return $this->enumOptions;
    }

    throw new PhutilInvalidStateException('setEnumOptions');
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

  public function isCustomType() {
    return !strncmp($this->getType(), 'custom:', 7);
  }

  public function getCustomObject() {
    if (!$this->customObject) {
      if (!$this->isCustomType()) {
        throw new Exception(pht('This option does not have a custom type!'));
      }
      $this->customObject = newv(substr($this->getType(), 7), array());
    }
    return $this->customObject;
  }

  public function getCustomData() {
    return $this->customData;
  }

  public function setCustomData($data) {
    $this->customData = $data;
    return $this;
  }

/* -(  PhabricatorMarkupInterface  )----------------------------------------- */

  public function getMarkupFieldKey($field) {
    return $this->getKey().':'.$field;
  }

  public function newMarkupEngine($field) {
    return PhabricatorMarkupEngine::newMarkupEngine(array());
  }

  public function getMarkupText($field) {
    switch ($field) {
      case 'description':
        $text = $this->getDescription();
        break;
      case 'summary':
        $text = $this->getSummary();
        break;
    }

    // TODO: We should probably implement this as a real Markup rule, but
    // markup rules are a bit of a mess right now and it doesn't hurt us to
    // fake this.
    $text = preg_replace(
      '/{{([^}]+)}}/',
      '[[/config/edit/\\1/ | \\1]]',
      $text);

    return $text;
  }

  public function didMarkupText($field, $output, PhutilMarkupEngine $engine) {
    return $output;
  }

  public function shouldUseMarkupCache($field) {
    return false;
  }

}
