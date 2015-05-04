<?php

final class ManiphestCustomFieldStatusParserTestCase
  extends PhabricatorTestCase {

  public function testParser() {
    $map = array(
      'quack quack quack' => array(),
      'T123' => array(),
      'Fixes T123' => array(
        array(
          'match' => 'Fixes T123',
          'prefix' => 'Fixes',
          'infix' => '',
          'monograms' => array('T123'),
          'suffix' => '',
          'offset' => 0,
        ),
      ),
      'Fixes T123, T124, and also some other bugs.' => array(
        array(
          'match' => 'Fixes T123, T124, ',
          'prefix' => 'Fixes',
          'infix' => '',
          'monograms' => array('T123', 'T124'),
          'suffix' => '',
          'offset' => 0,
        ),
      ),
      'Closes T1 as wontfix' => array(
        array(
          'match' => 'Closes T1 as wontfix',
          'prefix' => 'Closes',
          'infix' => '',
          'monograms' => array('T1'),
          'suffix' => 'as wontfix',
          'offset' => 0,
        ),
      ),
      'Fixes task T9' => array(
        array(
          'match' => 'Fixes task T9',
          'prefix' => 'Fixes',
          'infix' => 'task',
          'monograms' => array('T9'),
          'suffix' => '',
          'offset' => 0,
        ),
      ),
      'Fixes t2apps' => array(),
      'fixes a bug' => array(),
      'Prefixes T2' => array(),
      'Reopens T123' => array(
        array(
          'match' => 'Reopens T123',
          'prefix' => 'Reopens',
          'infix' => '',
          'monograms' => array('T123'),
          'suffix' => '',
          'offset' => 0,
        ),
      ),
      'Fixes T123, T456, and T789.' => array(
        array(
          'match' => 'Fixes T123, T456, and T789',
          'prefix' => 'Fixes',
          'infix' => '',
          'monograms' => array('T123', 'T456', 'T789'),
          'suffix' => '',
          'offset' => 0,
        ),
      ),
    );

    foreach ($map as $input => $expect) {
      $parser = new ManiphestCustomFieldStatusParser();
      $output = $parser->parseCorpus($input);

      $this->assertEqual($expect, $output, $input);
    }
  }

}
