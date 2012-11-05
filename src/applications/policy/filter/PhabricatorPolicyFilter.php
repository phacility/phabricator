<?php

final class PhabricatorPolicyFilter {

  private $viewer;
  private $objects;
  private $capabilities;
  private $raisePolicyExceptions;
  private $userProjects;

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

    $need_projects = array();
    foreach ($objects as $key => $object) {
      $object_capabilities = $object->getCapabilities();
      foreach ($capabilities as $capability) {
        if (!in_array($capability, $object_capabilities)) {
          throw new Exception(
            "Testing for capability '{$capability}' on an object which does ".
            "not have that capability!");
        }

        $policy = $object->getPolicy($capability);
        $type = phid_get_type($policy);
        if ($type == PhabricatorPHIDConstants::PHID_TYPE_PROJ) {
          $need_projects[] = $policy;
        }
      }
    }

    if ($need_projects) {
      $need_projects = array_unique($need_projects);

      // If projects have recursive policies, automatically fail them rather
      // than looping. This will fall back to automatic capabilities and
      // resolve the policies in a sensible way.
      static $querying_projects = array();
      foreach ($need_projects as $key => $project) {
        if (empty($querying_projects[$project])) {
          $querying_projects[$project] = true;
          continue;
        }
        unset($need_projects[$key]);
      }

      if ($need_projects) {
        $caught = null;
        try {
          $projects = id(new PhabricatorProjectQuery())
            ->setViewer($viewer)
            ->withMemberPHIDs(array($viewer->getPHID()))
            ->withPHIDs($need_projects)
            ->execute();
        } catch (Exception $ex) {
          $caught = $ex;
        }

        foreach ($need_projects as $key => $project) {
          unset($querying_projects[$project]);
        }

        if ($caught) {
          throw $caught;
        }

        $projects = mpull($projects, null, 'getPHID');
        $this->userProjects[$viewer->getPHID()] = $projects;
      }
    }

    foreach ($objects as $key => $object) {
      $object_capabilities = $object->getCapabilities();
      foreach ($capabilities as $capability) {
        if (!$this->checkCapability($object, $capability)) {
          // If we're missing any capability, move on to the next object.
          continue 2;
        }

        // If we make it here, we have all of the required capabilities.
        $filtered[$key] = $object;
      }
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
        $type = phid_get_type($policy);
        if ($type == PhabricatorPHIDConstants::PHID_TYPE_PROJ) {
          if (isset($this->userProjects[$viewer->getPHID()][$policy])) {
            return true;
          } else {
            $this->rejectObject($object, $policy, $capability);
          }
        } else {
          throw new Exception("Object has unknown policy '{$policy}'!");
        }
    }

    return false;
  }

  private function rejectImpossiblePolicy(
    PhabricatorPolicyInterface $object,
    $policy,
    $capability) {

    if (!$this->raisePolicyExceptions) {
      return;
    }

    // TODO: clean this up
    $verb = $capability;

    throw new PhabricatorPolicyException(
      "This object has an impossible {$verb} policy.");
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
        $type = phid_get_type($policy);
        if ($type == PhabricatorPHIDConstants::PHID_TYPE_PROJ) {
          $handle = PhabricatorObjectHandleData::loadOneHandle(
            $policy,
            $this->viewer);
          $who = "To {$verb} this object, you must be a member of project ".
                 "'".$handle->getFullName()."'.";
        } else {
          $who = "It is unclear who can {$verb} this object.";
        }
        break;
    }

    throw new PhabricatorPolicyException("{$message} {$who}");
  }
}
