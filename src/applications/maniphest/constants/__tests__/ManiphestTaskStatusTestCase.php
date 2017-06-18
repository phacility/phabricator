<?php

final class ManiphestTaskStatusTestCase extends PhabricatorTestCase {

  public function testManiphestStatusConstants() {
    $map = array(
      'y' => true,
      'closed' => true,
      'longlonglong' => true,
      'duplicate2' => true,

      '' => false,
      'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' =>
         false,
      '.' => false,
      ' ' => false,
      'ABCD' => true,
      'a b c ' => false,
      '1' => false,
      '111' => false,
      '11a' => true,
    );

    foreach ($map as $input => $expect) {
      $this->assertEqual(
        $expect,
        ManiphestTaskStatus::isValidStatusConstant($input),
        pht('Validate "%s"', $input));
    }
  }

  public function testManiphestStatusConfigValidation() {
    $this->assertConfigValid(
      false,
      pht('Empty'),
      array());

    // This is a minimal, valid configuration.

    $valid = array(
      'open' => array(
        'name' => pht('Open'),
        'special' => 'default',
      ),
      'closed' => array(
        'name' => pht('Closed'),
        'special' => 'closed',
        'closed' => true,
      ),
      'duplicate' => array(
        'name' => pht('Duplicate'),
        'special' => 'duplicate',
        'closed' => true,
      ),
    );
    $this->assertConfigValid(true, pht('Minimal Valid Config'), $valid);

    // We should raise on a bad key.
    $bad_key = $valid;
    $bad_key['!'] = array('name' => pht('Exclaim'));
    $this->assertConfigValid(false, pht('Bad Key'), $bad_key);

    // We should raise on a value type.
    $bad_type = $valid;
    $bad_type['other'] = 'other';
    $this->assertConfigValid(false, pht('Bad Value Type'), $bad_type);

    // We should raise on an unknown configuration key.
    $invalid_key = $valid;
    $invalid_key['open']['imaginary'] = 'unicorn';
    $this->assertConfigValid(false, pht('Invalid Key'), $invalid_key);

    // We should raise on two statuses with the same special.
    $double_close = $valid;
    $double_close['finished'] = array(
      'name' => pht('Finished'),
      'special' => 'closed',
      'closed' => true,
    );
    $this->assertConfigValid(false, pht('Duplicate Special'), $double_close);

    // We should raise if any of the special statuses are missing.
    foreach ($valid as $key => $config) {
      $missing = $valid;
      unset($missing[$key]);
      $this->assertConfigValid(false, pht('Missing Special'), $missing);
    }

    // The "default" special should be an open status.
    $closed_default = $valid;
    $closed_default['open']['closed'] = true;
    $this->assertConfigValid(false, pht('Closed Default'), $closed_default);

    // The "closed" special should be a closed status.
    $open_closed = $valid;
    $open_closed['closed']['closed'] = false;
    $this->assertConfigValid(false, pht('Open Closed'), $open_closed);

    // The "duplicate" special should be a closed status.
    $open_duplicate = $valid;
    $open_duplicate['duplicate']['closed'] = false;
    $this->assertConfigValid(false, pht('Open Duplicate'), $open_duplicate);
  }

  private function assertConfigValid($expect, $name, array $config) {
    $caught = null;
    try {
      ManiphestTaskStatus::validateConfiguration($config);
    } catch (Exception $ex) {
      $caught = $ex;
    }

    $this->assertEqual(
      $expect,
      !($caught instanceof Exception),
      pht('Validation of "%s"', $name));
  }

}
