<?php

final class DifferentialLintStatus extends Phobject {

  const LINT_NONE       = 0;
  const LINT_OKAY       = 1;
  const LINT_WARN       = 2;
  const LINT_FAIL       = 3;
  const LINT_SKIP       = 4;
  const LINT_AUTO_SKIP  = 6;

  private $value;

  public static function newStatusFromValue($value) {
    $status = new self();
    $status->value = $value;
    return $status;
  }

  public function getValue() {
    return $this->value;
  }

  public function getName() {
    $name = $this->getLintStatusProperty('name');

    if ($name === null) {
      $name = pht('Unknown Lint Status ("%s")', $this->getValue());
    }

    return $name;
  }

  public function getIconIcon() {
    return $this->getLintStatusProperty('icon.icon');
  }

  public function getIconColor() {
    return $this->getLintStatusProperty('icon.color');
  }

  public static function getStatusMap() {
    $results = array();

    foreach (self::newLintStatusMap() as $value => $ignored) {
      $results[$value] = self::newStatusFromValue($value);
    }

    return $results;
  }

  private function getLintStatusProperty($key, $default = null) {
    $map = self::newLintStatusMap();
    $properties = idx($map, $this->getValue(), array());
    return idx($properties, $key, $default);
  }

  private static function newLintStatusMap() {
    return array(
      self::LINT_NONE => array(
        'name' => pht('No Lint Coverage'),
        'icon.icon' => 'fa-ban',
        'icon.color' => 'grey',
      ),
      self::LINT_OKAY => array(
        'name' => pht('Lint Passed'),
        'icon.icon' => 'fa-check',
        'icon.color' => 'green',
      ),
      self::LINT_WARN => array(
        'name' => pht('Lint Warnings'),
        'icon.icon' => 'fa-exclamation-triangle',
        'icon.color' => 'yellow',
      ),
      self::LINT_FAIL => array(
        'name' => pht('Lint Errors'),
        'icon.icon' => 'fa-times',
        'icon.color' => 'red',
      ),
      self::LINT_SKIP => array(
        'name' => pht('Lint Skipped'),
        'icon.icon' => 'fa-fast-forward',
        'icon.color' => 'blue',
      ),
      self::LINT_AUTO_SKIP => array(
        'name' => pht('Lint Not Applicable'),
        'icon.icon' => 'fa-code',
        'icon.color' => 'grey',
      ),
    );
  }

}
