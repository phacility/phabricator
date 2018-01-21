<?php

final class PhabricatorAuthPasswordTestCase extends PhabricatorTestCase {

  protected function getPhabricatorTestCaseConfiguration() {
    return array(
      self::PHABRICATOR_TESTCONFIG_BUILD_STORAGE_FIXTURES => true,
    );
  }

  public function testCompare() {
    $password1 = new PhutilOpaqueEnvelope('hunter2');
    $password2 = new PhutilOpaqueEnvelope('hunter3');

    $user = $this->generateNewTestUser();
    $type = PhabricatorAuthPassword::PASSWORD_TYPE_TEST;

    $pass = PhabricatorAuthPassword::initializeNewPassword($user, $type)
      ->setPassword($password1, $user)
      ->save();

    $this->assertTrue(
      $pass->comparePassword($password1, $user),
      pht('Good password should match.'));

    $this->assertFalse(
      $pass->comparePassword($password2, $user),
      pht('Bad password should not match.'));
  }

  public function testPasswordEngine() {
    $password1 = new PhutilOpaqueEnvelope('the quick');
    $password2 = new PhutilOpaqueEnvelope('brown fox');

    $user = $this->generateNewTestUser();
    $test_type = PhabricatorAuthPassword::PASSWORD_TYPE_TEST;
    $account_type = PhabricatorAuthPassword::PASSWORD_TYPE_ACCOUNT;
    $content_source = $this->newContentSource();

    $engine = id(new PhabricatorAuthPasswordEngine())
      ->setViewer($user)
      ->setContentSource($content_source)
      ->setPasswordType($test_type)
      ->setObject($user);

    $account_engine = id(new PhabricatorAuthPasswordEngine())
      ->setViewer($user)
      ->setContentSource($content_source)
      ->setPasswordType($account_type)
      ->setObject($user);

    // We haven't set any passwords yet, so both passwords should be
    // invalid.
    $this->assertFalse($engine->isValidPassword($password1));
    $this->assertFalse($engine->isValidPassword($password2));

    $pass = PhabricatorAuthPassword::initializeNewPassword($user, $test_type)
      ->setPassword($password1, $user)
      ->save();

    // The password should now be valid.
    $this->assertTrue($engine->isValidPassword($password1));
    $this->assertFalse($engine->isValidPassword($password2));

    // But, since the password is a "test" password, it should not be a valid
    // "account" password.
    $this->assertFalse($account_engine->isValidPassword($password1));
    $this->assertFalse($account_engine->isValidPassword($password2));

    // Both passwords are unique for the "test" engine, since an active
    // password of a given type doesn't collide with itself.
    $this->assertTrue($engine->isUniquePassword($password1));
    $this->assertTrue($engine->isUniquePassword($password2));

    // The "test" password is no longer unique for the "account" engine.
    $this->assertFalse($account_engine->isUniquePassword($password1));
    $this->assertTrue($account_engine->isUniquePassword($password2));

    $this->revokePassword($user, $pass);

    // Now that we've revoked the password, it should no longer be valid.
    $this->assertFalse($engine->isValidPassword($password1));
    $this->assertFalse($engine->isValidPassword($password2));

    // But it should be a revoked password.
    $this->assertTrue($engine->isRevokedPassword($password1));
    $this->assertFalse($engine->isRevokedPassword($password2));

    // It should be revoked for both roles: revoking a "test" password also
    // prevents you from choosing it as a new "account" password.
    $this->assertTrue($account_engine->isRevokedPassword($password1));
    $this->assertFalse($account_engine->isValidPassword($password2));

    // The revoked password makes this password non-unique for all account
    // types.
    $this->assertFalse($engine->isUniquePassword($password1));
    $this->assertTrue($engine->isUniquePassword($password2));
    $this->assertFalse($account_engine->isUniquePassword($password1));
    $this->assertTrue($account_engine->isUniquePassword($password2));
  }

  private function revokePassword(
    PhabricatorUser $actor,
    PhabricatorAuthPassword $password) {

    $content_source = $this->newContentSource();
    $revoke_type = PhabricatorAuthPasswordRevokeTransaction::TRANSACTIONTYPE;

    $xactions = array();

    $xactions[] = $password->getApplicationTransactionTemplate()
      ->setTransactionType($revoke_type)
      ->setNewValue(true);

    $editor = $password->getApplicationTransactionEditor()
      ->setActor($actor)
      ->setContinueOnNoEffect(true)
      ->setContinueOnMissingFields(true)
      ->setContentSource($content_source)
      ->applyTransactions($password, $xactions);
  }

}
