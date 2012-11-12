<?php

final class LiskDAOTestCase extends PhabricatorTestCase {

  public function testCheckProperty() {
    $scratch = new HarbormasterScratchTable();
    $scratch->getData();

    $this->assertException('Exception', array($this, 'getData'));
  }

  public function getData() {
    $isolation = new LiskIsolationTestDAO();
    $isolation->getData();
  }

}
