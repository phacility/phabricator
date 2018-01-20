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

}
