<?php

final class HarbormasterBuildUnitMessage
  extends HarbormasterDAO
  implements PhabricatorPolicyInterface {

  protected $buildTargetPHID;
  protected $engine;
  protected $namespace;
  protected $name;
  protected $nameIndex;
  protected $result;
  protected $duration;
  protected $properties = array();

  private $buildTarget = self::ATTACHABLE;

  const FORMAT_TEXT = 'text';
  const FORMAT_REMARKUP = 'remarkup';

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
      'format' => array(
        'type' => 'optional string',
        'description' => pht(
          'Format for the text provided in "details". Valid values are '.
          '"text" (default) or "remarkup". This controls how test details '.
          'are rendered when shown to users.'),
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

    $format = idx($dict, 'format');
    if ($format) {
      $obj->setProperty('format', $format);
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
        'nameIndex' => 'bytes12',
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

  public function getUnitMessageDetailsFormat() {
    return $this->getProperty('format', self::FORMAT_TEXT);
  }

  public function newUnitMessageDetailsView(
    PhabricatorUser $viewer,
    $summarize = false) {

    $format = $this->getUnitMessageDetailsFormat();

    $is_text = ($format !== self::FORMAT_REMARKUP);
    $is_remarkup = ($format === self::FORMAT_REMARKUP);
    $message = null;

    $full_details = $this->getUnitMessageDetails();
    $byte_length = strlen($full_details);

    $text_limit = 1024 * 2;
    $remarkup_limit = 1024 * 8;

    if (!$byte_length) {
      if ($summarize) {
        return null;
      }
      $message = phutil_tag('em', array(), pht('No details provided.'));
    } else if ($summarize) {
      if ($is_text) {
        $details = id(new PhutilUTF8StringTruncator())
          ->setMaximumBytes($text_limit)
          ->truncateString($full_details);
        $details = phutil_split_lines($details);

        $limit = 3;
        if (count($details) > $limit) {
          $details = array_slice($details, 0, $limit);
        }

        $details = implode('', $details);
      } else {
        if ($byte_length > $remarkup_limit) {
          $uri = $this->getURI();

          if ($uri) {
            $link = phutil_tag(
              'a',
              array(
                'href' => $uri,
                'target' => '_blank',
              ),
              pht('View Details'));
          } else {
            $link = null;
          }

          $message = array();
          $message[] = phutil_tag(
            'em',
            array(),
            pht('This test has too much data to display inline.'));
          if ($link) {
            $message[] = $link;
          }

          $message = phutil_implode_html(" \xC2\xB7 ", $message);
        } else {
          $details = $full_details;
        }
      }
    } else {
      $details = $full_details;
    }

    require_celerity_resource('harbormaster-css');

    $classes = array();
    $classes[] = 'harbormaster-unit-details';

    if ($message !== null) {
      // If we have a message, show that instead of rendering any test details.
      $details = $message;
    } else if ($is_remarkup) {
      $details = new PHUIRemarkupView($viewer, $details);
    } else {
      $classes[] = 'harbormaster-unit-details-text';
      $classes[] = 'PhabricatorMonospaced';
    }

    $warning = null;
    if (!$summarize) {
      $warnings = array();

      if ($is_remarkup && ($byte_length > $remarkup_limit)) {
        $warnings[] = pht(
          'This test result has %s bytes of Remarkup test details. Remarkup '.
          'blocks longer than %s bytes are not rendered inline when showing '.
          'test summaries.',
          new PhutilNumber($byte_length),
          new PhutilNumber($remarkup_limit));
      }

      if ($warnings) {
        $warning = id(new PHUIInfoView())
          ->setSeverity(PHUIInfoView::SEVERITY_WARNING)
          ->setErrors($warnings);
      }
    }

    $content = phutil_tag(
      'div',
      array(
        'class' => implode(' ', $classes),
      ),
      $details);

    return array(
      $warning,
      $content,
    );
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

  public function getURI() {
    $id = $this->getID();

    if (!$id) {
      return null;
    }

    return urisprintf(
      '/harbormaster/unit/view/%d/',
      $id);
  }

  public function save() {
    if ($this->nameIndex === null) {
      $this->nameIndex = HarbormasterString::newIndex($this->getName());
    }

    // See T13088. While we're letting installs do online migrations to avoid
    // downtime, don't populate the "name" column for new writes. New writes
    // use the "HarbormasterString" table instead.
    $old_name = $this->name;
    $this->name = '';

    $caught = null;
    try {
      $result = parent::save();
    } catch (Exception $ex) {
      $caught = $ex;
    }

    $this->name = $old_name;

    if ($caught) {
      throw $caught;
    }

    return $result;
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return PhabricatorPolicies::getMostOpenPolicy();
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }

}
