<?php

final class PhabricatorAccessControlTestCase extends PhabricatorTestCase {

  protected function getPhabricatorTestCaseConfiguration() {
    return array(
      self::PHABRICATOR_TESTCONFIG_BUILD_STORAGE_FIXTURES => true,
    );
  }

  public function testControllerAccessControls() {
    $root = dirname(phutil_get_library_root('phabricator'));
    require_once $root.'/support/PhabricatorStartup.php';

    $application_configuration = new AphrontDefaultApplicationConfiguration();

    $host = 'meow.example.com';

    $_SERVER['REQUEST_METHOD'] = 'GET';

    $request = id(new AphrontRequest($host, '/'))
      ->setApplicationConfiguration($application_configuration)
      ->setRequestData(array());

    $controller = new PhabricatorTestController();
    $controller->setRequest($request);

    $u_public = id(new PhabricatorUser())
      ->setUsername('public');

    $u_unverified = $this->generateNewTestUser()
      ->setUsername('unverified')
      ->save();
    $u_unverified->setIsEmailVerified(0)->save();

    $u_normal = $this->generateNewTestUser()
      ->setUsername('normal')
      ->save();

    $u_disabled = $this->generateNewTestUser()
      ->setIsDisabled(true)
      ->setUsername('disabled')
      ->save();

    $u_admin = $this->generateNewTestUser()
      ->setIsAdmin(true)
      ->setUsername('admin')
      ->save();

    $u_notapproved = $this->generateNewTestUser()
      ->setIsApproved(0)
      ->setUsername('notapproved')
      ->save();

    $env = PhabricatorEnv::beginScopedEnv();
    $env->overrideEnvConfig('phabricator.base-uri', 'http://'.$host);
    $env->overrideEnvConfig('policy.allow-public', false);
    $env->overrideEnvConfig('auth.require-email-verification', false);
    $env->overrideEnvConfig('security.require-multi-factor-auth', false);


    // Test standard defaults.

    $this->checkAccess(
      pht('Default'),
      id(clone $controller),
      $request,
      array(
        $u_normal,
        $u_admin,
        $u_unverified,
      ),
      array(
        $u_public,
        $u_disabled,
        $u_notapproved,
      ));


    // Test email verification.

    $env->overrideEnvConfig('auth.require-email-verification', true);
    $this->checkAccess(
      pht('Email Verification Required'),
      id(clone $controller),
      $request,
      array(
        $u_normal,
        $u_admin,
      ),
      array(
        $u_unverified,
        $u_public,
        $u_disabled,
        $u_notapproved,
      ));

    $this->checkAccess(
      pht('Email Verification Required, With Exception'),
      id(clone $controller)->setConfig('email', false),
      $request,
      array(
        $u_normal,
        $u_admin,
        $u_unverified,
      ),
      array(
        $u_public,
        $u_disabled,
        $u_notapproved,
      ));
    $env->overrideEnvConfig('auth.require-email-verification', false);


    // Test admin access.

    $this->checkAccess(
      pht('Admin Required'),
      id(clone $controller)->setConfig('admin', true),
      $request,
      array(
        $u_admin,
      ),
      array(
        $u_normal,
        $u_unverified,
        $u_public,
        $u_disabled,
        $u_notapproved,
      ));


    // Test disabled access.

    $this->checkAccess(
      pht('Allow Disabled'),
      id(clone $controller)->setConfig('enabled', false),
      $request,
      array(
        $u_normal,
        $u_unverified,
        $u_admin,
        $u_disabled,
        $u_notapproved,
      ),
      array(
        $u_public,
      ));


    // Test no login required.

    $this->checkAccess(
      pht('No Login Required'),
      id(clone $controller)->setConfig('login', false),
      $request,
      array(
        $u_normal,
        $u_unverified,
        $u_admin,
        $u_public,
      ),
      array(
        $u_disabled,
        $u_notapproved,
      ));


    // Test public access.

    $this->checkAccess(
      pht('Public Access'),
      id(clone $controller)->setConfig('public', true),
      $request,
      array(
        $u_normal,
        $u_unverified,
        $u_admin,
      ),
      array(
        $u_disabled,
        $u_public,
      ));

    $env->overrideEnvConfig('policy.allow-public', true);
    $this->checkAccess(
      pht('Public + configured'),
      id(clone $controller)->setConfig('public', true),
      $request,
      array(
        $u_normal,
        $u_unverified,
        $u_admin,
        $u_public,
      ),
      array(
        $u_disabled,
        $u_notapproved,
      ));
    $env->overrideEnvConfig('policy.allow-public', false);


    $app = PhabricatorApplication::getByClass('PhabricatorTestApplication');
    $app->reset();
    $app->setPolicy(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicies::POLICY_NOONE);

    $app_controller = id(clone $controller)->setCurrentApplication($app);

    $this->checkAccess(
      pht('Application Controller'),
      $app_controller,
      $request,
      array(
      ),
      array(
        $u_normal,
        $u_unverified,
        $u_admin,
        $u_public,
        $u_disabled,
        $u_notapproved,
      ));

    $this->checkAccess(
      pht('Application Controller'),
      id(clone $app_controller)->setConfig('login', false),
      $request,
      array(
        $u_normal,
        $u_unverified,
        $u_admin,
        $u_public,
      ),
      array(
        $u_disabled,
        $u_notapproved,
      ));
  }

  private function checkAccess(
    $label,
    $controller,
    $request,
    array $yes,
    array $no) {

    foreach ($yes as $user) {
      $request->setUser($user);
      $uname = $user->getUsername();

      try {
        $result = id(clone $controller)->willBeginExecution();
      } catch (Exception $ex) {
        $result = $ex;
      }

      $this->assertTrue(
        ($result === null),
        pht("Expect user '%s' to be allowed access to '%s'.", $uname, $label));
    }

    foreach ($no as $user) {
      $request->setUser($user);
      $uname = $user->getUsername();

      try {
        $result = id(clone $controller)->willBeginExecution();
      } catch (Exception $ex) {
        $result = $ex;
      }

      $this->assertFalse(
        ($result === null),
        pht("Expect user '%s' to be denied access to '%s'.", $uname, $label));
    }
  }

}
