<?php

final class PhabricatorUserEditorTestCase extends PhabricatorTestCase {

  protected function getPhabricatorTestCaseConfiguration() {
    return array(
      self::PHABRICATOR_TESTCONFIG_BUILD_STORAGE_FIXTURES => true,
    );
  }

  public function testRegistrationEmailOK() {
    $env = PhabricatorEnv::beginScopedEnv();
    $env->overrideEnvConfig('auth.email-domains', array('example.com'));

    $this->registerUser(
      'PhabricatorUserEditorTestCaseOK',
      'PhabricatorUserEditorTest@example.com');

    $this->assertTrue(true);
  }

  public function testRegistrationEmailInvalid() {
    $env = PhabricatorEnv::beginScopedEnv();
    $env->overrideEnvConfig('auth.email-domains', array('example.com'));

    $prefix = str_repeat('a', PhabricatorUserEmail::MAX_ADDRESS_LENGTH);
    $email = $prefix.'@evil.com@example.com';

    try {
      $this->registerUser('PhabricatorUserEditorTestCaseInvalid', $email);
    } catch (Exception $ex) {
      $caught = $ex;
    }

    $this->assertTrue($caught instanceof Exception);
  }

  public function testRegistrationEmailDomain() {
    $env = PhabricatorEnv::beginScopedEnv();
    $env->overrideEnvConfig('auth.email-domains', array('example.com'));

    $caught = null;
    try {
      $this->registerUser(
        'PhabricatorUserEditorTestCaseDomain',
        'PhabricatorUserEditorTest@whitehouse.gov');
    } catch (Exception $ex) {
      $caught = $ex;
    }

    $this->assertTrue($caught instanceof Exception);
  }

  public function testRegistrationEmailApplicationEmailCollide() {
    $app_email = 'bugs@whitehouse.gov';
    $app_email_object =
      PhabricatorMetaMTAApplicationEmail::initializeNewAppEmail(
        $this->generateNewTestUser());
    $app_email_object->setAddress($app_email);
    $app_email_object->setApplicationPHID('test');
    $app_email_object->save();

    $caught = null;
    try {
      $this->registerUser(
        'PhabricatorUserEditorTestCaseDomain',
        $app_email);
    } catch (Exception $ex) {
      $caught = $ex;
    }
    $this->assertTrue($caught instanceof Exception);
  }

  private function registerUser($username, $email) {
    $user = id(new PhabricatorUser())
      ->setUsername($username)
      ->setRealname($username);

    $email = id(new PhabricatorUserEmail())
      ->setAddress($email)
      ->setIsVerified(0);

    id(new PhabricatorUserEditor())
      ->setActor($user)
      ->createNewUser($user, $email);
  }

}
