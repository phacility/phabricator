<?php

final class HarbormasterBuildLintMessage
  extends HarbormasterDAO {

  protected $buildTargetPHID;
  protected $path;
  protected $line;
  protected $characterOffset;
  protected $code;
  protected $severity;
  protected $name;
  protected $properties = array();

  private $buildTarget = self::ATTACHABLE;

  public static function initializeNewLintMessage(
    HarbormasterBuildTarget $build_target) {
    return id(new HarbormasterBuildLintMessage())
      ->setBuildTargetPHID($build_target->getPHID());
  }

  public static function newFromDictionary(
    HarbormasterBuildTarget $build_target,
    array $dict) {

    $obj = self::initializeNewLintMessage($build_target);

    $spec = array(
      'path' => 'string',
      'line' => 'optional int',
      'char' => 'optional int',
      'code' => 'string',
      'severity' => 'string',
      'name' => 'string',
      'description' => 'optional string',
    );

    // We're just going to ignore extra keys for now, to make it easier to
    // add stuff here later on.
    $dict = array_select_keys($dict, array_keys($spec));
    PhutilTypeSpec::checkMap($dict, $spec);

    $obj->setPath($dict['path']);
    $obj->setLine(idx($dict, 'line'));
    $obj->setCharacterOffset(idx($dict, 'char'));
    $obj->setCode($dict['code']);
    $obj->setSeverity($dict['severity']);
    $obj->setName($dict['name']);

    $description = idx($dict, 'description');
    if (strlen($description)) {
      $obj->setProperty('description', $description);
    }

    return $obj;
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'properties' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'path' => 'text',
        'line' => 'uint32?',
        'characterOffset' => 'uint32?',
        'code' => 'text32',
        'severity' => 'text32',
        'name' => 'text255',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_target' => array(
          'columns' => array('buildTargetPHID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function attachBuildTarget(HarbormasterBuildTarget $build_target) {
    $this->buildTarget = $build_target;
    return $this;
  }

  public function getBuildTarget() {
    return $this->assertAttached($this->buildTarget);
  }

  public function getProperty($key, $default = null) {
    return idx($this->properties, $key, $default);
  }

  public function setProperty($key, $value) {
    $this->properties[$key] = $value;
    return $this;
  }

  public function getSortKey() {
    // TODO: Maybe use more numeric values after T6861.
    $map = array(
      ArcanistLintSeverity::SEVERITY_ERROR => 'A',
      ArcanistLintSeverity::SEVERITY_WARNING => 'B',
      ArcanistLintSeverity::SEVERITY_AUTOFIX => 'C',
      ArcanistLintSeverity::SEVERITY_ADVICE => 'Y',
      ArcanistLintSeverity::SEVERITY_DISABLED => 'Z',
    );

    $severity = idx($map, $this->getSeverity(), 'N');

    $parts = array(
      $severity,
      $this->getPath(),
      sprintf('%08d', $this->getLine()),
      $this->getCode(),
    );

    return implode("\0", $parts);
  }

}
