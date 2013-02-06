<?php

final class PhabricatorFileTestCase extends PhabricatorTestCase {

  public function getPhabricatorTestCaseConfiguration() {
    return array(
      self::PHABRICATOR_TESTCONFIG_BUILD_STORAGE_FIXTURES => true,
    );
  }

  public function testFileStorageReadWrite() {
    $engine = new PhabricatorTestStorageEngine();

    $data = Filesystem::readRandomCharacters(64);

    $params = array(
      'name' => 'test.dat',
      'storageEngines' => array(
        $engine,
      ),
    );

    $file = PhabricatorFile::newFromFileData($data, $params);

    // Test that the storage engine worked, and was the target of the write. We
    // don't actually care what the data is (future changes may compress or
    // encrypt it), just that it exists in the test storage engine.
    $engine->readFile($file->getStorageHandle());

    // Now test that we get the same data back out.
    $this->assertEqual($data, $file->loadFileData());
  }


  public function testFileStorageDelete() {
    $engine = new PhabricatorTestStorageEngine();

    $data = Filesystem::readRandomCharacters(64);

    $params = array(
      'name' => 'test.dat',
      'storageEngines' => array(
        $engine,
      ),
    );

    $file = PhabricatorFile::newFromFileData($data, $params);
    $handle = $file->getStorageHandle();
    $file->delete();

    $caught = null;
    try {
      $engine->readFile($handle);
    } catch (Exception $ex) {
      $caught = $ex;
    }

    $this->assertEqual(true, $caught instanceof Exception);
  }

}
