<?php

final class PhabricatorAuditRequestStatus extends Phobject {

  const AUDIT_REQUIRED          = 'audit-required';
  const CONCERNED               = 'concerned';
  const ACCEPTED                = 'accepted';
  const AUDIT_REQUESTED         = 'requested';
  const RESIGNED                = 'resigned';

  private $key;

  public static function newForStatus($status) {
    $result = new self();
    $result->key = $status;
    return $result;
  }

  public function getIconIcon() {
    return $this->getMapProperty('icon');
  }

  public function getIconColor() {
    return $this->getMapProperty('icon.color');
  }

  public function getStatusName() {
    $name = $this->getMapProperty('name');
    if ($name !== null) {
      return $name;
    }

    return pht('Unknown Audit Request Status ("%s")', $this->key);
  }

  public function getStatusValue() {
    return $this->key;
  }

  public function getStatusValueForConduit() {
    return $this->getMapProperty('value.conduit');
  }

  public function isResigned() {
    return ($this->key === self::RESIGNED);
  }

  private function getMapProperty($key, $default = null) {
    $map = self::newStatusMap();
    $spec = idx($map, $this->key, array());
    return idx($spec, $key, $default);
  }

  private static function newStatusMap() {
    return array(
      self::AUDIT_REQUIRED => array(
        'name' => pht('Audit Required'),
        'icon' => 'fa-exclamation-circle',
        'icon.color' => 'orange',
        'value.conduit' => 'audit-required',
      ),
      self::AUDIT_REQUESTED => array(
        'name' => pht('Audit Requested'),
        'icon' => 'fa-exclamation-circle',
        'icon.color' => 'orange',
        'value.conduit' => 'audit-requested',
      ),
      self::CONCERNED => array(
        'name' => pht('Concern Raised'),
        'icon' => 'fa-times-circle',
        'icon.color' => 'red',
        'value.conduit' => 'concern-raised',
      ),
      self::ACCEPTED => array(
        'name' => pht('Accepted'),
        'icon' => 'fa-check-circle',
        'icon.color' => 'green',
        'value.conduit' => 'accepted',
      ),
      self::RESIGNED => array(
        'name' => pht('Resigned'),
        'icon' => 'fa-times',
        'icon.color' => 'grey',
        'value.conduit' => 'resigned',
      ),
    );
  }

}
