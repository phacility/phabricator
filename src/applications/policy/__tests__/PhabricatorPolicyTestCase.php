<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

final class PhabricatorPolicyTestCase extends PhabricatorTestCase {

  /**
   * Verify that any user can view an object with POLICY_PUBLIC.
   */
  public function testPublicPolicyEnabled() {
    $env = PhabricatorEnv::beginScopedEnv();
    $env->overrideEnvConfig('policy.allow-public', true);

    $this->expectVisibility(
      $this->buildObject(PhabricatorPolicies::POLICY_PUBLIC),
      array(
        'public'  => true,
        'user'    => true,
        'admin'   => true,
      ),
      'Public Policy (Enabled in Config)');
  }


  /**
   * Verify that POLICY_PUBLIC is interpreted as POLICY_USER when public
   * policies are disallowed.
   */
  public function testPublicPolicyDisabled() {
    $env = PhabricatorEnv::beginScopedEnv();
    $env->overrideEnvConfig('policy.allow-public', false);

    $this->expectVisibility(
      $this->buildObject(PhabricatorPolicies::POLICY_PUBLIC),
      array(
        'public'  => false,
        'user'    => true,
        'admin'   => true,
      ),
      'Public Policy (Disabled in Config)');
  }


  /**
   * Verify that any logged-in user can view an object with POLICY_USER, but
   * logged-out users can not.
   */
  public function testUsersPolicy() {
    $this->expectVisibility(
      $this->buildObject(PhabricatorPolicies::POLICY_USER),
      array(
        'public'  => false,
        'user'    => true,
        'admin'   => true,
      ),
      'User Policy');
  }


  /**
   * Verify that only administrators can view an object with POLICY_ADMIN.
   */
  public function testAdminPolicy() {
    $this->expectVisibility(
      $this->buildObject(PhabricatorPolicies::POLICY_ADMIN),
      array(
        'public'  => false,
        'user'    => false,
        'admin'   => true,
      ),
      'Admin Policy');
  }


  /**
   * Verify that no one can view an object with POLICY_NOONE.
   */
  public function testNoOnePolicy() {
    $this->expectVisibility(
      $this->buildObject(PhabricatorPolicies::POLICY_NOONE),
      array(
        'public'  => false,
        'user'    => false,
        'admin'   => false,
      ),
      'No One Policy');

  }


  /**
   * Test an object for visibility across multiple user specifications.
   */
  private function expectVisibility(
    PhabricatorPolicyTestObject $object,
    array $map,
    $description) {

    foreach ($map as $spec => $expect) {
      $viewer = $this->buildUser($spec);

      $query = new PhabricatorPolicyTestQuery();
      $query->setResults(array($object));
      $query->setViewer($viewer);

      $caught = null;
      try {
        $result = $query->executeOne();
      } catch (PhabricatorPolicyException $ex) {
        $caught = $ex;
      }

      if ($expect) {
        $this->assertEqual(
          $object,
          $result,
          "{$description} with user {$spec} should succeed.");
      } else {
        $this->assertEqual(
          true,
          $caught instanceof PhabricatorPolicyException,
          "{$description} with user {$spec} should fail.");
      }
    }
  }


  /**
   * Build a test object to spec.
   */
  private function buildObject($policy) {
    $object = new PhabricatorPolicyTestObject();
    $object->setCapabilities(
      array(
        PhabricatorPolicyCapability::CAN_VIEW,
      ));
    $object->setPolicies(
      array(
        PhabricatorPolicyCapability::CAN_VIEW => $policy,
      ));

    return $object;
  }


  /**
   * Build a test user to spec.
   */
  private function buildUser($spec) {
    $user = new PhabricatorUser();

    switch ($spec) {
      case 'public':
        break;
      case 'user':
        $user->setPHID(1);
        break;
      case 'admin':
        $user->setPHID(1);
        $user->setIsAdmin(true);
        break;
      default:
        throw new Exception("Unknown user spec '{$spec}'.");
    }

    return $user;
  }

}
