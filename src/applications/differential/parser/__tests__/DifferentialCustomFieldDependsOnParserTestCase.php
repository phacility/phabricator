<?php

final class DifferentialCustomFieldDependsOnParserTestCase
  extends PhabricatorTestCase {

  public function testParser() {
    $map = array(
      'quack quack quack' => array(),
      'D123' => array(),
      'depends on D123' => array(
        array(
          'match' => 'depends on D123',
          'prefix' => 'depends on',
          'infix' => '',
          'monograms' => array('D123'),
          'suffix' => '',
          'offset' => 0,
        ),
      ),
      'depends on D123.' => array(
        array(
          'match' => 'depends on D123',
          'prefix' => 'depends on',
          'infix' => '',
          'monograms' => array('D123'),
          'suffix' => '',
          'offset' => 0,
        ),
      ),
      'depends on D123, d124' => array(
        array(
          'match' => 'depends on D123, d124',
          'prefix' => 'depends on',
          'infix' => '',
          'monograms' => array('D123', 'd124'),
          'suffix' => '',
          'offset' => 0,
        ),
      ),
      'depends on rev D123' => array(
        array(
          'match' => 'depends on rev D123',
          'prefix' => 'depends on',
          'infix' => 'rev',
          'monograms' => array('D123'),
          'suffix' => '',
          'offset' => 0,
        ),
      ),
      'depends on duck' => array(
      ),
      'depends on D123abc' => array(
      ),
    );

    foreach ($map as $input => $expect) {
      $parser = new DifferentialCustomFieldDependsOnParser();
      $output = $parser->parseCorpus($input);

      $this->assertEqual($expect, $output, $input);
    }
  }

}
