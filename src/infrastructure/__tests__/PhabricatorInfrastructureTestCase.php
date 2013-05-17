<?php

final class PhabricatorInfrastructureTestCase
  extends PhabricatorTestCase {

  /**
   * This is more of an acceptance test case instead of a unittest. It verifies
   * that all symbols can be loaded correctly. It can catch problems like
   * missing methods in descendants of abstract base classes.
   */
  public function testEverythingImplemented() {
    id(new PhutilSymbolLoader())->selectAndLoadSymbols();
  }

  public function testApplicationsInstalled() {
    $all = PhabricatorApplication::getAllApplications();
    $installed = PhabricatorApplication::getAllInstalledApplications();

    $this->assertEqual(
      count($all),
      count($installed),
      'In test cases, all applications should default to installed.');
  }


}

