<?php

final class PhabricatorOwnersAuditRule
  extends Phobject {

  const AUDITING_NONE = 'none';
  const AUDITING_NO_OWNER = 'audit';
  const AUDITING_UNREVIEWED = 'unreviewed';
  const AUDITING_NO_OWNER_AND_UNREVIEWED = 'uninvolved-unreviewed';
  const AUDITING_ALL = 'all';

  private $key;
  private $spec;

  public static function newFromState($key) {
    $specs = self::newSpecifications();
    $spec = idx($specs, $key, array());

    $rule = new self();
    $rule->key = $key;
    $rule->spec = $spec;

    return $rule;
  }

  public function getKey() {
    return $this->key;
  }

  public function getDisplayName() {
    return idx($this->spec, 'name', $this->key);
  }

  public function getIconIcon() {
    return idx($this->spec, 'icon.icon');
  }

  public static function newSelectControlMap() {
    $specs = self::newSpecifications();
    return ipull($specs, 'name');
  }

  public static function getStorageValueFromAPIValue($value) {
    $specs = self::newSpecifications();

    $map = array();
    foreach ($specs as $key => $spec) {
      $deprecated = idx($spec, 'deprecated', array());
      if (isset($deprecated[$value])) {
        return $key;
      }
    }

    return $value;
  }

  public static function getModernValueMap() {
    $specs = self::newSpecifications();

    $map = array();
    foreach ($specs as $key => $spec) {
      $map[$key] = pht('"%s"', $key);
    }

    return $map;
  }

  public static function getDeprecatedValueMap() {
    $specs = self::newSpecifications();

    $map = array();
    foreach ($specs as $key => $spec) {
      $deprecated_map = idx($spec, 'deprecated', array());
      foreach ($deprecated_map as $deprecated_key => $label) {
        $map[$deprecated_key] = $label;
      }
    }

    return $map;
  }

  private static function newSpecifications() {
    return array(
      self::AUDITING_NONE => array(
        'name' => pht('No Auditing'),
        'icon.icon' => 'fa-ban',
        'deprecated' => array(
          '' => pht('"" (empty string)'),
          '0' => '"0"',
        ),
      ),
      self::AUDITING_UNREVIEWED => array(
        'name' => pht('Audit Unreviewed Commits'),
        'icon.icon' => 'fa-check',
      ),
      self::AUDITING_NO_OWNER => array(
        'name' => pht('Audit Commits With No Owner Involvement'),
        'icon.icon' => 'fa-check',
        'deprecated' => array(
          '1' => '"1"',
        ),
      ),
      self::AUDITING_NO_OWNER_AND_UNREVIEWED => array(
        'name' => pht(
          'Audit Unreviewed Commits and Commits With No Owner Involvement'),
        'icon.icon' => 'fa-check',
      ),
      self::AUDITING_ALL => array(
        'name' => pht('Audit All Commits'),
        'icon.icon' => 'fa-check',
      ),
    );
  }



}
