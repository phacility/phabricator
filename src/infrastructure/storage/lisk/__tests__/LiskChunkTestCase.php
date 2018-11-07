<?php

final class LiskChunkTestCase extends PhabricatorTestCase {

  public function testSQLChunking() {
    $fragments = array(
      'a',
      'a',
      'b',
      'b',
      'ccc',
      'dd',
      'e',
    );

    $this->assertEqual(
      array(
        array('a'),
        array('a'),
        array('b'),
        array('b'),
        array('ccc'),
        array('dd'),
        array('e'),
      ),
      PhabricatorLiskDAO::chunkSQL($fragments, 2));


    $fragments = array(
      'a',
      'a',
      'a',
      'XX',
      'a',
      'a',
      'a',
      'a',
    );

    $this->assertEqual(
      array(
        array('a', 'a', 'a'),
        array('XX', 'a', 'a'),
        array('a', 'a'),
      ),
      PhabricatorLiskDAO::chunkSQL($fragments, 8));


    $fragments = array(
      'xxxxxxxxxx',
      'yyyyyyyyyy',
      'a',
      'b',
      'c',
      'zzzzzzzzzz',
    );

    $this->assertEqual(
      array(
        array('xxxxxxxxxx'),
        array('yyyyyyyyyy'),
        array('a', 'b', 'c'),
        array('zzzzzzzzzz'),
      ),
      PhabricatorLiskDAO::chunkSQL($fragments, 8));
  }

}
