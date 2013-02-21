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

  public function testFileStorageUploadDifferentFiles() {
    $engine = new PhabricatorTestStorageEngine();

    $data = Filesystem::readRandomCharacters(64);
    $other_data = Filesystem::readRandomCharacters(64);

    $params = array(
      'name' => 'test.dat',
      'storageEngines' => array(
        $engine,
      ),
    );

    $first_file = PhabricatorFile::newFromFileData($data, $params);

    $second_file = PhabricatorFile::newFromFileData($other_data, $params);

    // Test that the the second file uses  different storage handle from
    // the first file.
    $first_handle = $first_file->getStorageHandle();
    $second_handle = $second_file->getStorageHandle();

    $this->assertEqual(true, ($first_handle != $second_handle));
  }


  public function testFileStorageUploadSameFile() {
    $engine = new PhabricatorTestStorageEngine();

    $data = Filesystem::readRandomCharacters(64);

    $params = array(
      'name' => 'test.dat',
      'storageEngines' => array(
        $engine,
      ),
    );

    $first_file = PhabricatorFile::newFromFileData($data, $params);

    $second_file = PhabricatorFile::newFromFileData($data, $params);

    // Test that the the second file uses the same storage handle as
    // the first file.
    $handle = $first_file->getStorageHandle();
    $second_handle = $second_file->getStorageHandle();

    $this->assertEqual($handle, $second_handle);
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

  public function testFileStorageDeleteSharedHandle() {
    $engine = new PhabricatorTestStorageEngine();

    $data = Filesystem::readRandomCharacters(64);

    $params = array(
      'name' => 'test.dat',
      'storageEngines' => array(
        $engine,
      ),
    );

    $first_file = PhabricatorFile::newFromFileData($data, $params);
    $second_file = PhabricatorFile::newFromFileData($data, $params);
    $first_file->delete();

    $this->assertEqual($data, $second_file->loadFileData());
  }

  public function testReadWriteTtlFiles() {
    $engine = new PhabricatorTestStorageEngine();

    $data = Filesystem::readRandomCharacters(64);

    $ttl = (time() + 60 * 60 * 24);

    $params = array(
      'name' => 'test.dat',
      'ttl'  => ($ttl),
      'storageEngines' => array(
        $engine,
      ),
    );

    $file = PhabricatorFile::newFromFileData($data, $params);
    $this->assertEqual($ttl, $file->getTTL());
  }

}
