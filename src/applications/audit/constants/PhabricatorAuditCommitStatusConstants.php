<?php

final class PhabricatorAuditCommitStatusConstants extends Phobject {

  private $key;
  private $spec = array();

  const MODERN_NONE = 'none';
  const MODERN_NEEDS_AUDIT = 'needs-audit';
  const MODERN_CONCERN_RAISED = 'concern-raised';
  const MODERN_PARTIALLY_AUDITED = 'partially-audited';
  const MODERN_AUDITED = 'audited';
  const MODERN_NEEDS_VERIFICATION = 'needs-verification';

  public static function newModernKeys(array $values) {
    $map = self::getMap();

    $modern = array();
    foreach ($map as $key => $spec) {
      if (isset($spec['legacy'])) {
        $modern[$spec['legacy']] = $key;
      }
    }

    foreach ($values as $key => $value) {
      $values[$key] = idx($modern, $value, $value);
    }

    return $values;
  }

  public static function newForStatus($status) {
    $result = new self();

    $result->key = $status;

    $map = self::getMap();
    if (isset($map[$status])) {
      $result->spec = $map[$status];
    }

    return $result;
  }

  public function getKey() {
    return $this->key;
  }

  public function getIcon() {
    return idx($this->spec, 'icon');
  }

  public function getColor() {
    return idx($this->spec, 'color');
  }

  public function getName() {
    return idx($this->spec, 'name', pht('Unknown ("%s")', $this->key));
  }

  public function isNoAudit() {
    return ($this->key == self::MODERN_NONE);
  }

  public function isNeedsAudit() {
    return ($this->key == self::MODERN_NEEDS_AUDIT);
  }

  public function isConcernRaised() {
    return ($this->key == self::MODERN_CONCERN_RAISED);
  }

  public function isNeedsVerification() {
    return ($this->key == self::MODERN_NEEDS_VERIFICATION);
  }

  public function isPartiallyAudited() {
    return ($this->key == self::MODERN_PARTIALLY_AUDITED);
  }

  public function isAudited() {
    return ($this->key == self::MODERN_AUDITED);
  }

  public function getIsClosed() {
    return idx($this->spec, 'closed');
  }

  public static function getOpenStatusConstants() {
    $constants = array();
    foreach (self::getMap() as $key => $map) {
      if (!$map['closed']) {
        $constants[] = $key;
      }
    }
    return $constants;
  }

  public static function newOptions() {
    $map = self::getMap();
    return ipull($map, 'name');
  }

  public static function newDeprecatedOptions() {
    $map = self::getMap();

    $results = array();
    foreach ($map as $key => $spec) {
      if (isset($spec['legacy'])) {
        $results[$spec['legacy']] = $key;
      }
    }

    return $results;
  }

  private static function getMap() {
    return array(
      self::MODERN_NONE => array(
        'name' => pht('No Audits'),
        'legacy' => 0,
        'icon' => 'fa-check',
        'color' => 'bluegrey',
        'closed' => true,
      ),
      self::MODERN_NEEDS_AUDIT => array(
        'name' => pht('Audit Required'),
        'legacy' => 1,
        'icon' => 'fa-exclamation-circle',
        'color' => 'orange',
        'closed' => false,
      ),
      self::MODERN_CONCERN_RAISED => array(
        'name' => pht('Concern Raised'),
        'legacy' => 2,
        'icon' => 'fa-times-circle',
        'color' => 'red',
        'closed' => false,
      ),
      self::MODERN_PARTIALLY_AUDITED => array(
        'name' => pht('Partially Audited'),
        'legacy' => 3,
        'icon' => 'fa-check-circle-o',
        'color' => 'yellow',
        'closed' => false,
      ),
      self::MODERN_AUDITED => array(
        'name' => pht('Audited'),
        'legacy' => 4,
        'icon' => 'fa-check-circle',
        'color' => 'green',
        'closed' => true,
      ),
      self::MODERN_NEEDS_VERIFICATION => array(
        'name' => pht('Needs Verification'),
        'legacy' => 5,
        'icon' => 'fa-refresh',
        'color' => 'indigo',
        'closed' => false,
      ),
    );
  }
}
