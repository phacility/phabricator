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

    // Reload the account. The user should be able to view and edit it, and
    // should be a member.

    $account = head($accounts);
    $account = id(new PhortuneAccountQuery())
      ->setViewer($user)
      ->withPHIDs(array($account->getPHID()))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();

    $this->assertEqual(true, ($account instanceof PhortuneAccount));
    $this->assertEqual(array($user->getPHID()), $account->getMemberPHIDs());
  }

}
