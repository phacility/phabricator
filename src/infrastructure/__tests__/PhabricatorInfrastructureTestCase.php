<?php

final class PhabricatorInfrastructureTestCase
  extends PhabricatorTestCase {

  /**
   * This is more of an acceptance test case instead of a unittest. It verifies
   * that all symbols can be loaded correctly. It can catch problem like missing
   * methods in descendants of abstract base classes.
   */
  public function testEverythingImplemented() {
    // Note that we don't have a try catch block around the following because,
    // when it fails, it will cause a HPHP or PHP fatal which won't be caught
    // by try catch.
    $every_class = id(new PhutilSymbolLoader())->selectAndLoadSymbols();
  }
}

