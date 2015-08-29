<?php

final class DifferentialCustomFieldRevertsParserTestCase
  extends PhabricatorTestCase {

  public function testParser() {
    $map = array(
      'quack quack quack' => array(),

      // Git default message.
      'This reverts commit 1234abcd.' => array(
        array(
          'match' => 'reverts commit 1234abcd',
          'prefix' => 'reverts',
          'infix' => 'commit',
          'monograms' => array('1234abcd'),
          'suffix' => '',
          'offset' => 5,
        ),
      ),

      // Mercurial default message.
      'Backed out changeset 1234abcd.' => array(
        array(
          'match' => 'Backed out changeset 1234abcd',
          'prefix' => 'Backed out',
          'infix' => 'changeset',
          'monograms' => array('1234abcd'),
          'suffix' => '',
          'offset' => 0,
        ),
      ),

      'this undoes 1234abcd, 5678efab. they were bad' => array(
        array(
          'match' => 'undoes 1234abcd, 5678efab',
          'prefix' => 'undoes',
          'infix' => '',
          'monograms' => array('1234abcd', '5678efab'),
          'suffix' => '',
          'offset' => 5,
        ),
      ),

      'Reverts 123' => array(
        array(
          'match' => 'Reverts 123',
          'prefix' => 'Reverts',
          'infix' => '',
          'monograms' => array('123'),
          'suffix' => '',
          'offset' => 0,
        ),
      ),


      'Reverts r123' => array(
        array(
          'match' => 'Reverts r123',
          'prefix' => 'Reverts',
          'infix' => '',
          'monograms' => array('r123'),
          'suffix' => '',
          'offset' => 0,
        ),
      ),

      "Backs out commit\n99\n100" => array(
        array(
          'match' => "Backs out commit\n99\n100",
          'prefix' => 'Backs out',
          'infix' => 'commit',
          'monograms' => array('99', '100'),
          'suffix' => '',
          'offset' => 0,
        ),
      ),

      // This tests a degenerate regex behavior, see T9268.
      'Reverts aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaz' => array(),

      "This doesn't revert anything" => array(),
      'nonrevert of r11' => array(),
      'fixed a bug' => array(),
    );

    foreach ($map as $input => $expect) {
      $parser = new DifferentialCustomFieldRevertsParser();
      $output = $parser->parseCorpus($input);

      $this->assertEqual($expect, $output, $input);
    }
  }

}
