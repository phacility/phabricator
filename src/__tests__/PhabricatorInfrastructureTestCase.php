<?php

final class PhabricatorInfrastructureTestCase extends PhabricatorTestCase {

  protected function getPhabricatorTestCaseConfiguration() {
    return array(
      self::PHABRICATOR_TESTCONFIG_BUILD_STORAGE_FIXTURES => true,
    );
  }

  /**
   * This is more of an acceptance test case instead of a unit test. It verifies
   * that all symbols can be loaded correctly. It can catch problems like
   * missing methods in descendants of abstract base classes.
   */
  public function testEverythingImplemented() {
    id(new PhutilSymbolLoader())->selectAndLoadSymbols();
    $this->assertTrue(true);
  }

  /**
   * This is more of an acceptance test case instead of a unit test. It verifies
   * that all the library map is up-to-date.
   */
  public function testLibraryMap() {
    $library = phutil_get_current_library_name();
    $root = phutil_get_library_root($library);

    $new_library_map = id(new PhutilLibraryMapBuilder($root))
      ->buildMap();

    $bootloader = PhutilBootloader::getInstance();
    $old_library_map = $bootloader->getLibraryMapWithoutExtensions($library);
    unset($old_library_map[PhutilLibraryMapBuilder::LIBRARY_MAP_VERSION_KEY]);

    $this->assertEqual(
      $new_library_map,
      $old_library_map,
      'The library map does not appear to be up-to-date. Try '.
      'rebuilding the map with `arc liberate`.');
  }

  public function testApplicationsInstalled() {
    $all = PhabricatorApplication::getAllApplications();
    $installed = PhabricatorApplication::getAllInstalledApplications();

    $this->assertEqual(
      count($all),
      count($installed),
      'In test cases, all applications should default to installed.');
  }

  public function testMySQLAgreesWithUsAboutBMP() {
    // Build a string with every BMP character in it, then insert it into MySQL
    // and read it back. We expect to get the same string out that we put in,
    // demonstrating that strings which pass our BMP checks are also valid in
    // MySQL and no silent data truncation will occur.

    $buf = '';

    for ($ii = 0x01; $ii <= 0x7F; $ii++) {
      $buf .= chr($ii);
    }

    for ($ii = 0xC2; $ii <= 0xDF; $ii++) {
      for ($jj = 0x80; $jj <= 0xBF; $jj++) {
        $buf .= chr($ii).chr($jj);
      }
    }

    // NOTE: This is \xE0\xA0\xZZ.
    for ($ii = 0xE0; $ii <= 0xE0; $ii++) {
      for ($jj = 0xA0; $jj <= 0xBF; $jj++) {
        for ($kk = 0x80; $kk <= 0xBF; $kk++) {
          $buf .= chr($ii).chr($jj).chr($kk);
        }
      }
    }

    // NOTE: This is \xE1\xZZ\xZZ through \xEF\xZZ\xZZ.
    for ($ii = 0xE1; $ii <= 0xEF; $ii++) {
      for ($jj = 0x80; $jj <= 0xBF; $jj++) {
        for ($kk = 0x80; $kk <= 0xBF; $kk++) {
          $buf .= chr($ii).chr($jj).chr($kk);
        }
      }
    }

    $this->assertEqual(194431, strlen($buf));
    $this->assertTrue(phutil_is_utf8_with_only_bmp_characters($buf));

    $write = id(new HarbormasterScratchTable())
      ->setData('all.utf8.bmp')
      ->setBigData($buf)
      ->save();

    $read = id(new HarbormasterScratchTable())->load($write->getID());

    $this->assertEqual($buf, $read->getBigData());
  }

  public function testRejectMySQLBMPQueries() {
    $table = new HarbormasterScratchTable();
    $conn_r = $table->establishConnection('w');

    $snowman = "\xE2\x98\x83";
    $gclef = "\xF0\x9D\x84\x9E";

    qsprintf($conn_r, 'SELECT %B', $snowman);
    qsprintf($conn_r, 'SELECT %s', $snowman);
    qsprintf($conn_r, 'SELECT %B', $gclef);

    $caught = null;
    try {
      qsprintf($conn_r, 'SELECT %s', $gclef);
    } catch (AphrontCharacterSetQueryException $ex) {
      $caught = $ex;
    }

    $this->assertTrue($caught instanceof AphrontCharacterSetQueryException);
  }

}
