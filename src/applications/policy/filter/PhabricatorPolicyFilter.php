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
  private $capability;
  private $raisePolicyExceptions;

  public function setViewer(PhabricatorUser $user) {
    $this->viewer = $user;
    return $this;
  }

  public function setCapability($capability) {
    $this->capability = $capability;
    return $this;
  }

  public function raisePolicyExceptions($raise) {
    $this->raisePolicyExceptions = $raise;
    return $this;
  }

  public function apply(array $objects) {
    assert_instances_of($objects, 'PhabricatorPolicyInterface');

    $viewer     = $this->viewer;
    $capability = $this->capability;

    if (!$viewer || !$capability) {
      throw new Exception(
        'Call setViewer() and setCapability() before apply()!');
    }

    $filtered = array();

    foreach ($objects as $key => $object) {
      $object_capabilities = $object->getCapabilities();

      if (!in_array($capability, $object_capabilities)) {
        throw new Exception(
          "Testing for capability '{$capability}' on an object which does not ".
          "have that capability!");
      }

      if ($object->hasAutomaticCapability($capability, $this->viewer)) {
        $filtered[$key] = $object;
        continue;
      }

      $policy = $object->getPolicy($capability);

      // If the object is set to "public" but that policy is disabled for this
      // install, restrict the policy to "user".
      if ($policy == PhabricatorPolicies::POLICY_PUBLIC) {
        if (!PhabricatorEnv::getEnvConfig('policy.allow-public')) {
          $policy = PhabricatorPolicies::POLICY_USER;
        }
      }

      switch ($policy) {
        case PhabricatorPolicies::POLICY_PUBLIC:
          $filtered[$key] = $object;
          break;
        case PhabricatorPolicies::POLICY_USER:
          if ($viewer->getPHID()) {
            $filtered[$key] = $object;
          } else {
            $this->rejectObject($object, $policy);
          }
          break;
        case PhabricatorPolicies::POLICY_ADMIN:
          if ($viewer->getIsAdmin()) {
            $filtered[$key] = $object;
          } else {
            $this->rejectObject($object, $policy);
          }
          break;
        case PhabricatorPolicies::POLICY_NOONE:
          $this->rejectObject($object, $policy);
          break;
        default:
          throw new Exception("Object has unknown policy '{$policy}'!");
      }
    }

    return $filtered;
  }

  private function rejectObject($object, $policy) {
    if (!$this->raisePolicyExceptions) {
      return;
    }

    $message = "You do not have permission to view this object.";

    switch ($policy) {
      case PhabricatorPolicies::POLICY_PUBLIC:
        $who = "This is curious, since anyone can view the object.";
        break;
      case PhabricatorPolicies::POLICY_USER:
        $who = "To view this object, you must be logged in.";
        break;
      case PhabricatorPolicies::POLICY_ADMIN:
        $who = "To view this object, you must be an administrator.";
        break;
      case PhabricatorPolicies::POLICY_NOONE:
        $who = "No one can view this object.";
        break;
      default:
        $who = "It is unclear who can view this object.";
        break;
    }

    throw new PhabricatorPolicyException("{$message} {$who}");
  }
}
