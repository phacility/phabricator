<?php

final class PhabricatorAuditCommitStatusConstants extends Phobject {

  const NONE                = 0;
  const NEEDS_AUDIT         = 1;
  const CONCERN_RAISED      = 2;
  const PARTIALLY_AUDITED   = 3;
  const FULLY_AUDITED       = 4;
  const NEEDS_VERIFICATION = 5;

  const MODERN_NONE = 'none';
  const MODERN_NEEDS_AUDIT = 'needs-audit';
  const MODERN_CONCERN_RAISED = 'concern-raised';
  const MODERN_PARTIALLY_AUDITED = 'partially-audited';
  const MODERN_AUDITED = 'audited';
  const MODERN_NEEDS_VERIFICATION = 'needs-verification';

  public static function getStatusNameMap() {
    $map = self::getMap();
    return ipull($map, 'name', 'legacy');
  }

  public static function getStatusName($code) {
    return idx(self::getStatusNameMap(), $code, pht('Unknown'));
  }

  public static function getOpenStatusConstants() {
    $constants = array();
    foreach (self::getMap() as $map) {
      if (!$map['closed']) {
        $constants[] = $map['legacy'];
      }
    }
    return $constants;
  }

  public static function getStatusColor($code) {
    $map = self::getMap();
    $map = ipull($map, 'color', 'legacy');
    return idx($map, $code);
  }

  public static function getStatusIcon($code) {
    $map = self::getMap();
    $map = ipull($map, 'icon', 'legacy');
    return idx($map, $code);
  }

  private static function getMap() {
    return array(
      self::MODERN_NONE => array(
        'name' => pht('No Audits'),
        'legacy' => self::NONE,
        'icon' => 'fa-check',
        'color' => 'bluegrey',
        'closed' => true,
      ),
      self::MODERN_NEEDS_AUDIT => array(
        'name' => pht('Audit Required'),
        'legacy' => self::NEEDS_AUDIT,
        'icon' => 'fa-exclamation-circle',
        'color' => 'orange',
        'closed' => false,
      ),
      self::MODERN_CONCERN_RAISED => array(
        'name' => pht('Concern Raised'),
        'legacy' => self::CONCERN_RAISED,
        'icon' => 'fa-times-circle',
        'color' => 'red',
        'closed' => false,
      ),
      self::MODERN_PARTIALLY_AUDITED => array(
        'name' => pht('Partially Audited'),
        'legacy' => self::PARTIALLY_AUDITED,
        'icon' => 'fa-check-circle-o',
        'color' => 'yellow',
        'closed' => false,
      ),
      self::MODERN_AUDITED => array(
        'name' => pht('Audited'),
        'legacy' => self::FULLY_AUDITED,
        'icon' => 'fa-check-circle',
        'color' => 'green',
        'closed' => true,
      ),
      self::MODERN_NEEDS_VERIFICATION => array(
        'name' => pht('Needs Verification'),
        'legacy' => self::NEEDS_VERIFICATION,
        'icon' => 'fa-refresh',
        'color' => 'indigo',
        'closed' => false,
      ),
    );
  }
}
