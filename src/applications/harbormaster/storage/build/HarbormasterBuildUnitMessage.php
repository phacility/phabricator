<?php

final class HarbormasterBuildUnitMessage
  extends HarbormasterDAO {

  protected $buildTargetPHID;
  protected $engine;
  protected $namespace;
  protected $name;
  protected $result;
  protected $duration;
  protected $properties = array();

  private $buildTarget = self::ATTACHABLE;

  public static function initializeNewUnitMessage(
    HarbormasterBuildTarget $build_target) {
    return id(new HarbormasterBuildUnitMessage())
      ->setBuildTargetPHID($build_target->getPHID());
  }

  public static function getParameterSpec() {
    return array(
      'name' => array(
        'type' => 'string',
        'description' => pht(
          'Short test name, like "ExampleTest".'),
      ),
      'result' => array(
        'type' => 'string',
        'description' => pht(
          'Result of the test.'),
      ),
      'namespace' => array(
        'type' => 'optional string',
        'description' => pht(
          'Optional namespace for this test. This is organizational and '.
          'is often a class or module name, like "ExampleTestCase".'),
      ),
      'engine' => array(
        'type' => 'optional string',
        'description' => pht(
          'Test engine running the test, like "JavascriptTestEngine". This '.
          'primarily prevents collisions between tests with the same name '.
          'in different test suites (for example, a Javascript test and a '.
          'Python test).'),
      ),
      'duration' => array(
        'type' => 'optional float|int',
        'description' => pht(
          'Runtime duration of the test, in seconds.'),
      ),
      'path' => array(
        'type' => 'optional string',
        'description' => pht(
          'Path to the file where the test is declared, relative to the '.
          'project root.'),
      ),
      'coverage' => array(
        'type' => 'optional map<string, wild>',
        'description' => pht(
          'Coverage information for this test.'),
      ),
      'details' => array(
        'type' => 'optional string',
        'description' => pht(
          'Additional human-readable information about the failure.'),
      ),
    );
  }

  public static function newFromDictionary(
    HarbormasterBuildTarget $build_target,
    array $dict) {

    $obj = self::initializeNewUnitMessage($build_target);

    $spec = self::getParameterSpec();
    $spec = ipull($spec, 'type');

    // We're just going to ignore extra keys for now, to make it easier to
    // add stuff here later on.
    $dict = array_select_keys($dict, array_keys($spec));
    PhutilTypeSpec::checkMap($dict, $spec);

    $obj->setEngine(idx($dict, 'engine', ''));
    $obj->setNamespace(idx($dict, 'namespace', ''));
    $obj->setName($dict['name']);
    $obj->setResult($dict['result']);
    $obj->setDuration((float)idx($dict, 'duration'));

    $path = idx($dict, 'path');
    if (strlen($path)) {
      $obj->setProperty('path', $path);
    }

    $coverage = idx($dict, 'coverage');
    if ($coverage) {
      $obj->setProperty('coverage', $coverage);
    }

    $details = idx($dict, 'details');
    if ($details) {
      $obj->setProperty('details', $details);
    }

    return $obj;
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'properties' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'engine' => 'text255',
        'namespace' => 'text255',
        'name' => 'text255',
        'result' => 'text32',
        'duration' => 'double?',
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

  public function getUnitMessageDetails() {
    return $this->getProperty('details', '');
  }

  public function getUnitMessageDisplayName() {
    $name = $this->getName();

    $namespace = $this->getNamespace();
    if (strlen($namespace)) {
      $name = $namespace.'::'.$name;
    }

    $engine = $this->getEngine();
    if (strlen($engine)) {
      $name = $engine.' > '.$name;
    }

    if (!strlen($name)) {
      return pht('Nameless Test (%d)', $this->getID());
    }

    return $name;
  }

  public function getSortKey() {
    $status = $this->getResult();
    $sort = HarbormasterUnitStatus::getUnitStatusSort($status);

    $parts = array(
      $sort,
      $this->getEngine(),
      $this->getNamespace(),
      $this->getName(),
      $this->getID(),
    );

    return implode("\0", $parts);
  }

}
