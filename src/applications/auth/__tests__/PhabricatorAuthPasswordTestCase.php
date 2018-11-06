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

  public function testPasswordBlocklisting() {
    $user = $this->generateNewTestUser();

    $user
      ->setUsername('iasimov')
      ->setRealName('Isaac Asimov')
      ->save();

    $test_type = PhabricatorAuthPassword::PASSWORD_TYPE_TEST;
    $content_source = $this->newContentSource();

    $engine = id(new PhabricatorAuthPasswordEngine())
      ->setViewer($user)
      ->setContentSource($content_source)
      ->setPasswordType($test_type)
      ->setObject($user);

    $env = PhabricatorEnv::beginScopedEnv();
    $env->overrideEnvConfig('account.minimum-password-length', 4);

    $passwords = array(
      'a23li432m9mdf' => true,

      // Empty.
      '' => false,

      // Password length tests.
      'xh3' => false,
      'xh32' => true,

      // In common password blocklist.
      'password1' => false,

      // Tests for the account identifier blocklist.
      'isaac' => false,
      'iasimov' => false,
      'iasimov1' => false,
      'asimov' => false,
      'iSaAc' => false,
      '32IASIMOV' => false,
      'i-am-iasimov-this-is-my-long-strong-password' => false,
      'iasimo' => false,

      // These are okay: although they're visually similar, they aren't mutual
      // substrings of any identifier.
      'iasimo1' => true,
      'isa1mov' => true,
    );

    foreach ($passwords as $password => $expect) {
      $this->assertBlocklistedPassword($engine, $password, $expect);
    }
  }

  private function assertBlocklistedPassword(
    PhabricatorAuthPasswordEngine $engine,
    $raw_password,
    $expect_valid) {

    $envelope_1 = new PhutilOpaqueEnvelope($raw_password);
    $envelope_2 = new PhutilOpaqueEnvelope($raw_password);

    $caught = null;
    try {
      $engine->checkNewPassword($envelope_1, $envelope_2);
    } catch (PhabricatorAuthPasswordException $exception) {
      $caught = $exception;
    }

    $this->assertEqual(
      $expect_valid,
      !($caught instanceof PhabricatorAuthPasswordException),
      pht('Validity of password "%s".', $raw_password));
  }


  public function testPasswordUpgrade() {
    $weak_hasher = new PhabricatorIteratedMD5PasswordHasher();

    // Make sure we have two different hashers, and that the second one is
    // stronger than iterated MD5. The most common reason this would fail is
    // if an install does not have bcrypt available.
    $strong_hasher = PhabricatorPasswordHasher::getBestHasher();
    if ($strong_hasher->getStrength() <= $weak_hasher->getStrength()) {
      $this->assertSkipped(
        pht(
          'Multiple password hashers of different strengths are not '.
          'available, so hash upgrading can not be tested.'));
    }

    $envelope = new PhutilOpaqueEnvelope('lunar1997');

    $user = $this->generateNewTestUser();
    $type = PhabricatorAuthPassword::PASSWORD_TYPE_TEST;
    $content_source = $this->newContentSource();

    $engine = id(new PhabricatorAuthPasswordEngine())
      ->setViewer($user)
      ->setContentSource($content_source)
      ->setPasswordType($type)
      ->setObject($user);

    $password = PhabricatorAuthPassword::initializeNewPassword($user, $type)
      ->setPasswordWithHasher($envelope, $user, $weak_hasher)
      ->save();

    $weak_name = $weak_hasher->getHashName();
    $strong_name = $strong_hasher->getHashName();

    // Since we explicitly used the weak hasher, the password should have
    // been hashed with it.
    $actual_hasher = $password->getHasher();
    $this->assertEqual($weak_name, $actual_hasher->getHashName());

    $is_valid = $engine
      ->setUpgradeHashers(false)
      ->isValidPassword($envelope, $user);
    $password->reload();

    // Since we disabled hasher upgrading, the password should not have been
    // rehashed.
    $this->assertTrue($is_valid);
    $actual_hasher = $password->getHasher();
    $this->assertEqual($weak_name, $actual_hasher->getHashName());

    $is_valid = $engine
      ->setUpgradeHashers(true)
      ->isValidPassword($envelope, $user);
    $password->reload();

    // Now that we enabled hasher upgrading, the password should have been
    // automatically rehashed into the stronger format.
    $this->assertTrue($is_valid);
    $actual_hasher = $password->getHasher();
    $this->assertEqual($strong_name, $actual_hasher->getHashName());

    // We should also have an "upgrade" transaction in the transaction record
    // now which records the two hasher names.
    $xactions = id(new PhabricatorAuthPasswordTransactionQuery())
      ->setViewer($user)
      ->withObjectPHIDs(array($password->getPHID()))
      ->withTransactionTypes(
        array(
          PhabricatorAuthPasswordUpgradeTransaction::TRANSACTIONTYPE,
        ))
      ->execute();

    $this->assertEqual(1, count($xactions));
    $xaction = head($xactions);

    $this->assertEqual($weak_name, $xaction->getOldValue());
    $this->assertEqual($strong_name, $xaction->getNewValue());

    $is_valid = $engine
      ->isValidPassword($envelope, $user);

    // Finally, the password should still be valid after all the dust has
    // settled.
    $this->assertTrue($is_valid);
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
