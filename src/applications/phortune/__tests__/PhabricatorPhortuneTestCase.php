<?php

final class PhabricatorPhortuneTestCase
  extends PhabricatorTestCase {

  protected function getPhabricatorTestCaseConfiguration() {
    return array(
      self::PHABRICATOR_TESTCONFIG_BUILD_STORAGE_FIXTURES => true,
    );
  }

  public function testNewPhortuneAccount() {
    $user = $this->generateNewTestUser();
    $content_source = $this->newContentSource();

    $accounts = PhortuneAccountQuery::loadAccountsForUser(
      $user,
      $content_source);

    $this->assertEqual(
      1,
      count($accounts),
      pht('Creation of default account for users with no accounts.'));
  }

}
