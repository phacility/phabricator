<?php

final class LiskChunkTestCase extends PhabricatorTestCase {

  public function testSQLChunking() {
    $fragments = array(
      'a', 'a',
      'b', 'b',
      'ccc',
      'dd',
      'e',
    );

    $this->assertEqual(
      array(
        'aa',
        'bb',
        'ccc',
        'dd',
        'e',
      ),
      PhabricatorLiskDAO::chunkSQL($fragments, '', 2));


    $fragments = array(
      'a', 'a', 'a', 'XX', 'a', 'a', 'a', 'a',
    );

    $this->assertEqual(
      array(
        'a, a, a',
        'XX, a, a',
        'a, a',
      ),
      PhabricatorLiskDAO::chunkSQL($fragments, ', ', 8));


    $fragments = array(
      'xxxxxxxxxx',
      'yyyyyyyyyy',
      'a', 'b', 'c',
      'zzzzzzzzzz',
    );

    $this->assertEqual(
      array(
        'xxxxxxxxxx',
        'yyyyyyyyyy',
        'a, b, c',
        'zzzzzzzzzz',
      ),
      PhabricatorLiskDAO::chunkSQL($fragments, ', ', 8));
  }

}
