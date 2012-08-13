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

final class PhabricatorPolicyFilter {

  private $viewer;
  private $objects;
  private $capabilities;
  private $raisePolicyExceptions;

  public static function mustRetainCapability(
    PhabricatorUser $user,
    PhabricatorPolicyInterface $object,
    $capability) {

    if (!self::hasCapability($user, $object, $capability)) {
      throw new Exception(
        "You can not make that edit, because it would remove your ability ".
        "to '{$capability}' the object.");
    }
  }

  public static function requireCapability(
    PhabricatorUser $user,
    PhabricatorPolicyInterface $object,
    $capability) {
    $filter = new PhabricatorPolicyFilter();
    $filter->setViewer($user);
    $filter->requireCapabilities(array($capability));
    $filter->raisePolicyExceptions(true);
    $filter->apply(array($object));
  }

  public static function hasCapability(
    PhabricatorUser $user,
    PhabricatorPolicyInterface $object,
    $capability) {

    $filter = new PhabricatorPolicyFilter();
    $filter->setViewer($user);
    $filter->requireCapabilities(array($capability));
    $result = $filter->apply(array($object));

    return (count($result) == 1);
  }

  public function setViewer(PhabricatorUser $user) {
    $this->viewer = $user;
    return $this;
  }

  public function requireCapabilities(array $capabilities) {
    $this->capabilities = $capabilities;
    return $this;
  }

  public function raisePolicyExceptions($raise) {
    $this->raisePolicyExceptions = $raise;
    return $this;
  }

  public function apply(array $objects) {
    assert_instances_of($objects, 'PhabricatorPolicyInterface');

    $viewer       = $this->viewer;
    $capabilities = $this->capabilities;

    if (!$viewer || !$capabilities) {
      throw new Exception(
        'Call setViewer() and requireCapabilities() before apply()!');
    }

    $filtered = array();

    foreach ($objects as $key => $object) {
      $object_capabilities = $object->getCapabilities();
      foreach ($capabilities as $capability) {
        if (!in_array($capability, $object_capabilities)) {
          throw new Exception(
            "Testing for capability '{$capability}' on an object which does ".
            "not have that capability!");
        }

        if (!$this->checkCapability($object, $capability)) {
          // If we're missing any capability, move on to the next object.
          continue 2;
        }
      }

      // If we make it here, we have all of the required capabilities.
      $filtered[$key] = $object;
    }

    return $filtered;
  }

  private function checkCapability(
    PhabricatorPolicyInterface $object,
    $capability) {

    $policy = $object->getPolicy($capability);

    if (!$policy) {
      // TODO: Formalize this somehow?
      $policy = PhabricatorPolicies::POLICY_USER;
    }

    if ($policy == PhabricatorPolicies::POLICY_PUBLIC) {
      // If the object is set to "public" but that policy is disabled for this
      // install, restrict the policy to "user".
      if (!PhabricatorEnv::getEnvConfig('policy.allow-public')) {
        $policy = PhabricatorPolicies::POLICY_USER;
      }

      // If the object is set to "public" but the capability is anything other
      // than "view", restrict the policy to "user".
      if ($capability != PhabricatorPolicyCapability::CAN_VIEW) {
        $policy = PhabricatorPolicies::POLICY_USER;
      }
    }

    $viewer = $this->viewer;

    if ($object->hasAutomaticCapability($capability, $viewer)) {
      return true;
    }

    switch ($policy) {
      case PhabricatorPolicies::POLICY_PUBLIC:
        return true;
      case PhabricatorPolicies::POLICY_USER:
        if ($viewer->getPHID()) {
          return true;
        } else {
          $this->rejectObject($object, $policy, $capability);
        }
        break;
      case PhabricatorPolicies::POLICY_ADMIN:
        if ($viewer->getIsAdmin()) {
          return true;
        } else {
          $this->rejectObject($object, $policy, $capability);
        }
        break;
      case PhabricatorPolicies::POLICY_NOONE:
        $this->rejectObject($object, $policy, $capability);
        break;
      default:
        throw new Exception("Object has unknown policy '{$policy}'!");
    }

    return false;
  }

  private function rejectObject($object, $policy, $capability) {
    if (!$this->raisePolicyExceptions) {
      return;
    }

    // TODO: clean this up
    $verb = $capability;

    $message = "You do not have permission to {$verb} this object.";

    switch ($policy) {
      case PhabricatorPolicies::POLICY_PUBLIC:
        $who = "This is curious, since anyone can {$verb} the object.";
        break;
      case PhabricatorPolicies::POLICY_USER:
        $who = "To {$verb} this object, you must be logged in.";
        break;
      case PhabricatorPolicies::POLICY_ADMIN:
        $who = "To {$verb} this object, you must be an administrator.";
        break;
      case PhabricatorPolicies::POLICY_NOONE:
        $who = "No one can {$verb} this object.";
        break;
      default:
        $who = "It is unclear who can {$verb} this object.";
        break;
    }

    throw new PhabricatorPolicyException("{$message} {$who}");
  }
}
