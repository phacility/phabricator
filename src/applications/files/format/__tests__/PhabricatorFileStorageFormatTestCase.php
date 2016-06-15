<?php

final class PhabricatorFileStorageFormatTestCase extends PhabricatorTestCase {

  protected function getPhabricatorTestCaseConfiguration() {
    return array(
      self::PHABRICATOR_TESTCONFIG_BUILD_STORAGE_FIXTURES => true,
    );
  }

  public function testRot13Storage() {
    $engine = new PhabricatorTestStorageEngine();
    $rot13_format = PhabricatorFileROT13StorageFormat::FORMATKEY;

    $data = 'The cow jumped over the full moon.';
    $expect = 'Gur pbj whzcrq bire gur shyy zbba.';

    $params = array(
      'name' => 'test.dat',
      'storageEngines' => array(
        $engine,
      ),
      'format' => $rot13_format,
    );

    $file = PhabricatorFile::newFromFileData($data, $params);

    // We should have a file stored as rot13, which reads back the input
    // data correctly.
    $this->assertEqual($rot13_format, $file->getStorageFormat());
    $this->assertEqual($data, $file->loadFileData());

    // The actual raw data in the storage engine should be encoded.
    $raw_data = $engine->readFile($file->getStorageHandle());
    $this->assertEqual($expect, $raw_data);
  }

}
