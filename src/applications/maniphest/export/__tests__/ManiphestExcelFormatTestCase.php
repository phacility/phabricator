<?php

final class ManiphestExcelFormatTestCase extends PhabricatorTestCase {

  public function testLoadAllFormats() {
    ManiphestExcelFormat::loadAllFormats();
    $this->assertTrue(true);
  }

}
