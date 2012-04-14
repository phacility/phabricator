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
  public function testPublicPolicy() {
    $viewer = new PhabricatorUser();

    $object = new PhabricatorPolicyTestObject();
    $object->setCapabilities(
      array(
        PhabricatorPolicyCapability::CAN_VIEW,
      ));
    $object->setPolicies(
      array(
        PhabricatorPolicyCapability::CAN_VIEW =>
          PhabricatorPolicies::POLICY_PUBLIC,
      ));

    $query = new PhabricatorPolicyTestQuery();
    $query->setResults(array($object));
    $query->setViewer($viewer);
    $result = $query->executeOne();

    $this->assertEqual($object, $result, 'Policy: Public');
  }


  /**
   * Verify that any logged-in user can view an object with POLICY_USER, but
   * logged-out users can not.
   */
  public function testUsersPolicy() {
    $viewer = new PhabricatorUser();

    $object = new PhabricatorPolicyTestObject();
    $object->setCapabilities(
      array(
        PhabricatorPolicyCapability::CAN_VIEW,
      ));
    $object->setPolicies(
      array(
        PhabricatorPolicyCapability::CAN_VIEW =>
          PhabricatorPolicies::POLICY_USER,
      ));

    $query = new PhabricatorPolicyTestQuery();
    $query->setResults(array($object));
    $query->setViewer($viewer);

    $caught = null;
    try {
      $query->executeOne();
    } catch (PhabricatorPolicyException $ex) {
      $caught = $ex;
    }

    $this->assertEqual(
      true,
      ($caught instanceof PhabricatorPolicyException),
      'Policy: Users rejects logged out users.');

    $viewer->setPHID(1);
    $result = $query->executeOne();
    $this->assertEqual(
      $object,
      $result,
      'Policy: Users');
  }


  /**
   * Verify that no one can view an object with POLICY_NOONE.
   */
  public function testNoOnePolicy() {
     $viewer = new PhabricatorUser();

    $object = new PhabricatorPolicyTestObject();
    $object->setCapabilities(
      array(
        PhabricatorPolicyCapability::CAN_VIEW,
      ));
    $object->setPolicies(
      array(
        PhabricatorPolicyCapability::CAN_VIEW =>
          PhabricatorPolicies::POLICY_NOONE,
      ));

    $query = new PhabricatorPolicyTestQuery();
    $query->setResults(array($object));
    $query->setViewer($viewer);

    $caught = null;
    try {
      $query->executeOne();
    } catch (PhabricatorPolicyException $ex) {
      $caught = $ex;
    }

    $this->assertEqual(
      true,
      ($caught instanceof PhabricatorPolicyException),
      'Policy: No One rejects logged out users.');

    $viewer->setPHID(1);

    $caught = null;
    try {
      $query->executeOne();
    } catch (PhabricatorPolicyException $ex) {
      $caught = $ex;
    }

    $this->assertEqual(
      true,
      ($caught instanceof PhabricatorPolicyException),
      'Policy: No One rejects logged-in users.');
  }

}
