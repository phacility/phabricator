<?php

final class ReleephEvent extends ReleephDAO {

  const TYPE_BRANCH_CREATE = 'branch-create';
  const TYPE_BRANCH_ACCESS = 'branch-access-change';

  protected $releephProjectID;
  protected $releephBranchID;
  protected $type;
  protected $epoch;
  protected $actorPHID;
  protected $details = array();

  public function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'details' => self::SERIALIZATION_JSON,
      ),
    ) + parent::getConfiguration();
  }

  public function getDetail($key, $default = null) {
    return idx($this->details, $key, $default);
  }

  public function setDetail($key, $value) {
    $this->details[$key] = $value;
    return $this;
  }

  protected function willSaveObject() {
    parent::willSaveObject();
    if (!$this->epoch) {
      $this->epoch = $this->dateCreated;
    }
  }

}
