<?php

final class DiffusionCommitAuditStatus extends Phobject {

  private $key;
  private $spec = array();

  const NONE = 'none';
  const NEEDS_AUDIT = 'needs-audit';
  const CONCERN_RAISED = 'concern-raised';
  const PARTIALLY_AUDITED = 'partially-audited';
  const AUDITED = 'audited';
  const NEEDS_VERIFICATION = 'needs-verification';

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

  public function getAnsiColor() {
    return idx($this->spec, 'color.ansi');
  }

  public function getName() {
    return idx($this->spec, 'name', pht('Unknown ("%s")', $this->key));
  }

  public function isNoAudit() {
    return ($this->key == self::NONE);
  }

  public function isNeedsAudit() {
    return ($this->key == self::NEEDS_AUDIT);
  }

  public function isConcernRaised() {
    return ($this->key == self::CONCERN_RAISED);
  }

  public function isNeedsVerification() {
    return ($this->key == self::NEEDS_VERIFICATION);
  }

  public function isPartiallyAudited() {
    return ($this->key == self::PARTIALLY_AUDITED);
  }

  public function isAudited() {
    return ($this->key == self::AUDITED);
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
      self::NONE => array(
        'name' => pht('No Audits'),
        'legacy' => 0,
        'icon' => 'fa-check',
        'color' => 'bluegrey',
        'closed' => true,
        'color.ansi' => null,
      ),
      self::NEEDS_AUDIT => array(
        'name' => pht('Audit Required'),
        'legacy' => 1,
        'icon' => 'fa-exclamation-circle',
        'color' => 'orange',
        'closed' => false,
        'color.ansi' => 'magenta',
      ),
      self::CONCERN_RAISED => array(
        'name' => pht('Concern Raised'),
        'legacy' => 2,
        'icon' => 'fa-times-circle',
        'color' => 'red',
        'closed' => false,
        'color.ansi' => 'red',
      ),
      self::PARTIALLY_AUDITED => array(
        'name' => pht('Partially Audited'),
        'legacy' => 3,
        'icon' => 'fa-check-circle-o',
        'color' => 'yellow',
        'closed' => false,
        'color.ansi' => 'yellow',
      ),
      self::AUDITED => array(
        'name' => pht('Audited'),
        'legacy' => 4,
        'icon' => 'fa-check-circle',
        'color' => 'green',
        'closed' => true,
        'color.ansi' => 'green',
      ),
      self::NEEDS_VERIFICATION => array(
        'name' => pht('Needs Verification'),
        'legacy' => 5,
        'icon' => 'fa-refresh',
        'color' => 'indigo',
        'closed' => false,
        'color.ansi' => 'magenta',
      ),
    );
  }
}
