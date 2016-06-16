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

  public function testAES256Storage() {
    $engine = new PhabricatorTestStorageEngine();

    $key_name = 'test.abcd';
    $key_text = 'abcdefghijklmnopABCDEFGHIJKLMNOP';

    PhabricatorKeyring::addKey(
      array(
        'name' => $key_name,
        'type' => 'aes-256-cbc',
        'material.base64' => base64_encode($key_text),
      ));

    $format = id(new PhabricatorFileAES256StorageFormat())
      ->selectMasterKey($key_name);

    $data = 'The cow jumped over the full moon.';

    $params = array(
      'name' => 'test.dat',
      'storageEngines' => array(
        $engine,
      ),
      'format' => $format,
    );

    $file = PhabricatorFile::newFromFileData($data, $params);

    // We should have a file stored as AES256.
    $format_key = $format->getStorageFormatKey();
    $this->assertEqual($format_key, $file->getStorageFormat());
    $this->assertEqual($data, $file->loadFileData());

    // The actual raw data in the storage engine should be encrypted. We
    // can't really test this, but we can make sure it's not the same as the
    // input data.
    $raw_data = $engine->readFile($file->getStorageHandle());
    $this->assertTrue($data !== $raw_data);
  }
}
