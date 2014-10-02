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

  public function testRejectMySQLNonUTF8Queries() {
    $table = new HarbormasterScratchTable();
    $conn_r = $table->establishConnection('w');

    $snowman = "\xE2\x98\x83";
    $invalid = "\xE6\x9D";

    qsprintf($conn_r, 'SELECT %B', $snowman);
    qsprintf($conn_r, 'SELECT %s', $snowman);
    qsprintf($conn_r, 'SELECT %B', $invalid);

    $caught = null;
    try {
      qsprintf($conn_r, 'SELECT %s', $invalid);
    } catch (AphrontCharacterSetQueryException $ex) {
      $caught = $ex;
    }

    $this->assertTrue($caught instanceof AphrontCharacterSetQueryException);
  }

}
