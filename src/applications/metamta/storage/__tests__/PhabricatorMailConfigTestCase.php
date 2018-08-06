<?php

final class PhabricatorMailConfigTestCase
  extends PhabricatorTestCase {

  public function testMailerPriorities() {
    $mailers = $this->newMailersWithConfig(
      array(
        array(
          'key' => 'A',
          'type' => 'test',
        ),
        array(
          'key' => 'B',
          'type' => 'test',
        ),
      ));

    $this->assertEqual(
      array('A', 'B'),
      mpull($mailers, 'getKey'));

    $mailers = $this->newMailersWithConfig(
      array(
        array(
          'key' => 'A',
          'priority' => 1,
          'type' => 'test',
        ),
        array(
          'key' => 'B',
          'priority' => 2,
          'type' => 'test',
        ),
      ));

    $this->assertEqual(
      array('B', 'A'),
      mpull($mailers, 'getKey'));

    $mailers = $this->newMailersWithConfig(
      array(
        array(
          'key' => 'A1',
          'priority' => 300,
          'type' => 'test',
        ),
        array(
          'key' => 'A2',
          'priority' => 300,
          'type' => 'test',
        ),
        array(
          'key' => 'B',
          'type' => 'test',
        ),
        array(
          'key' => 'C',
          'priority' => 400,
          'type' => 'test',
        ),
        array(
          'key' => 'D',
          'type' => 'test',
        ),
      ));

    // The "A" servers should be shuffled randomly, so either outcome is
    // acceptable.
    $option_1 = array('C', 'A1', 'A2', 'B', 'D');
    $option_2 = array('C', 'A2', 'A1', 'B', 'D');
    $actual = mpull($mailers, 'getKey');

    $this->assertTrue(($actual === $option_1) || ($actual === $option_2));

    // Make sure that when we're load balancing we actually send traffic to
    // both servers reasonably often.
    $saw_a1 = false;
    $saw_a2 = false;
    $attempts = 0;
    while (true) {
      $mailers = $this->newMailersWithConfig(
        array(
          array(
            'key' => 'A1',
            'priority' => 300,
            'type' => 'test',
          ),
          array(
            'key' => 'A2',
            'priority' => 300,
            'type' => 'test',
          ),
        ));

      $first_key = head($mailers)->getKey();

      if ($first_key == 'A1') {
        $saw_a1 = true;
      }

      if ($first_key == 'A2') {
        $saw_a2 = true;
      }

      if ($saw_a1 && $saw_a2) {
        break;
      }

      if ($attempts++ > 1024) {
        throw new Exception(
          pht(
            'Load balancing between two mail servers did not select both '.
            'servers after an absurd number of attempts.'));
      }
    }

    $this->assertTrue($saw_a1 && $saw_a2);
  }

  public function testMailerConstraints() {
    $config = array(
      array(
        'key' => 'X1',
        'type' => 'test',
      ),
      array(
        'key' => 'X1-in',
        'type' => 'test',
        'outbound' => false,
      ),
      array(
        'key' => 'X1-out',
        'type' => 'test',
        'inbound' => false,
      ),
      array(
        'key' => 'X1-void',
        'type' => 'test',
        'inbound' => false,
        'outbound' => false,
      ),
    );

    $mailers = $this->newMailersWithConfig(
      $config,
      array());
    $this->assertEqual(4, count($mailers));

    $mailers = $this->newMailersWithConfig(
      $config,
      array(
        'inbound' => true,
      ));
    $this->assertEqual(2, count($mailers));

    $mailers = $this->newMailersWithConfig(
      $config,
      array(
        'outbound' => true,
      ));
    $this->assertEqual(2, count($mailers));

    $mailers = $this->newMailersWithConfig(
      $config,
      array(
        'inbound' => true,
        'outbound' => true,
      ));
    $this->assertEqual(1, count($mailers));

    $mailers = $this->newMailersWithConfig(
      $config,
      array(
        'types' => array('test'),
      ));
    $this->assertEqual(4, count($mailers));

    $mailers = $this->newMailersWithConfig(
      $config,
      array(
        'types' => array('duck'),
      ));
    $this->assertEqual(0, count($mailers));
  }

  private function newMailersWithConfig(
    array $config,
    array $constraints = array()) {

    $env = PhabricatorEnv::beginScopedEnv();
    $env->overrideEnvConfig('cluster.mailers', $config);

    $mailers = PhabricatorMetaMTAMail::newMailers($constraints);

    unset($env);
    return $mailers;
  }

}
