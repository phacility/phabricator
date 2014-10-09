<?php

final class PhabricatorPolicyFilter {

  private $viewer;
  private $objects;
  private $capabilities;
  private $raisePolicyExceptions;
  private $userProjects;
  private $customPolicies = array();
  private $forcedPolicy;

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
    $filter = id(new PhabricatorPolicyFilter())
      ->setViewer($user)
      ->requireCapabilities(array($capability))
      ->raisePolicyExceptions(true)
      ->apply(array($object));
  }

  /**
   * Perform a capability check, acting as though an object had a specific
   * policy. This is primarily used to check if a policy is valid (for example,
   * to prevent users from editing away their ability to edit an object).
   *
   * Specifically, a check like this:
   *
   *   PhabricatorPolicyFilter::requireCapabilityWithForcedPolicy(
   *     $viewer,
   *     $object,
   *     PhabricatorPolicyCapability::CAN_EDIT,
   *     $potential_new_policy);
   *
   * ...will throw a @{class:PhabricatorPolicyException} if the new policy would
   * remove the user's ability to edit the object.
   *
   * @param PhabricatorUser   The viewer to perform a policy check for.
   * @param PhabricatorPolicyInterface The object to perform a policy check on.
   * @param string            Capability to test.
   * @param string            Perform the test as though the object has this
   *                          policy instead of the policy it actually has.
   * @return void
   */
  public static function requireCapabilityWithForcedPolicy(
    PhabricatorUser $viewer,
    PhabricatorPolicyInterface $object,
    $capability,
    $forced_policy) {

    id(new PhabricatorPolicyFilter())
      ->setViewer($viewer)
      ->requireCapabilities(array($capability))
      ->raisePolicyExceptions(true)
      ->forcePolicy($forced_policy)
      ->apply(array($object));
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

  public function forcePolicy($forced_policy) {
    $this->forcedPolicy = $forced_policy;
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

    // If the viewer is omnipotent, short circuit all the checks and just
    // return the input unmodified. This is an optimization; we know the
    // result already.
    if ($viewer->isOmnipotent()) {
      return $objects;
    }

    $filtered = array();
    $viewer_phid = $viewer->getPHID();

    if (empty($this->userProjects[$viewer_phid])) {
      $this->userProjects[$viewer_phid] = array();
    }

    $need_projects = array();
    $need_policies = array();
    foreach ($objects as $key => $object) {
      $object_capabilities = $object->getCapabilities();
      foreach ($capabilities as $capability) {
        if (!in_array($capability, $object_capabilities)) {
          throw new Exception(
            "Testing for capability '{$capability}' on an object which does ".
            "not have that capability!");
        }

        $policy = $this->getObjectPolicy($object, $capability);
        $type = phid_get_type($policy);
        if ($type == PhabricatorProjectProjectPHIDType::TYPECONST) {
          $need_projects[$policy] = $policy;
        }

        if ($type == PhabricatorPolicyPHIDTypePolicy::TYPECONST) {
          $need_policies[$policy] = $policy;
        }
      }
    }

    if ($need_policies) {
      $this->loadCustomPolicies(array_keys($need_policies));
    }

    // If we need projects, check if any of the projects we need are also the
    // objects we're filtering. Because of how project rules work, this is a
    // common case.
    if ($need_projects) {
      foreach ($objects as $object) {
        if ($object instanceof PhabricatorProject) {
          $project_phid = $object->getPHID();
          if (isset($need_projects[$project_phid])) {
            $is_member = $object->isUserMember($viewer_phid);
            $this->userProjects[$viewer_phid][$project_phid] = $is_member;
            unset($need_projects[$project_phid]);
          }
        }
      }
    }

    if ($need_projects) {
      $need_projects = array_unique($need_projects);

      // NOTE: We're using the omnipotent user here to avoid a recursive
      // descent into madness. We don't actually need to know if the user can
      // see these projects or not, since: the check is "user is member of
      // project", not "user can see project"; and membership implies
      // visibility anyway. Without this, we may load other projects and
      // re-enter the policy filter and generally create a huge mess.

      $projects = id(new PhabricatorProjectQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->withMemberPHIDs(array($viewer->getPHID()))
        ->withPHIDs($need_projects)
        ->execute();

      foreach ($projects as $project) {
        $this->userProjects[$viewer_phid][$project->getPHID()] = true;
      }
    }

    foreach ($objects as $key => $object) {
      $object_capabilities = $object->getCapabilities();
      foreach ($capabilities as $capability) {
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

    $policy = $this->getObjectPolicy($object, $capability);

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

      // If the object is set to "public" but the capability is not a public
      // capability, restrict the policy to "user".
      $capobj = PhabricatorPolicyCapability::getCapabilityByKey($capability);
      if (!$capobj || !$capobj->shouldAllowPublicPolicySetting()) {
        $policy = PhabricatorPolicies::POLICY_USER;
      }
    }

    $viewer = $this->viewer;

    if ($viewer->isOmnipotent()) {
      return true;
    }

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
        if ($type == PhabricatorProjectProjectPHIDType::TYPECONST) {
          if (!empty($this->userProjects[$viewer->getPHID()][$policy])) {
            return true;
          } else {
            $this->rejectObject($object, $policy, $capability);
          }
        } else if ($type == PhabricatorPeopleUserPHIDType::TYPECONST) {
          if ($viewer->getPHID() == $policy) {
            return true;
          } else {
            $this->rejectObject($object, $policy, $capability);
          }
        } else if ($type == PhabricatorPolicyPHIDTypePolicy::TYPECONST) {
          if ($this->checkCustomPolicy($policy)) {
            return true;
          } else {
            $this->rejectObject($object, $policy, $capability);
          }
        } else {
          // Reject objects with unknown policies.
          $this->rejectObject($object, false, $capability);
        }
    }

    return false;
  }

  public function rejectObject(
    PhabricatorPolicyInterface $object,
    $policy,
    $capability) {

    if (!$this->raisePolicyExceptions) {
      return;
    }

    if ($this->viewer->isOmnipotent()) {
      // Never raise policy exceptions for the omnipotent viewer. Although we
      // will never normally issue a policy rejection for the omnipotent
      // viewer, we can end up here when queries blanket reject objects that
      // have failed to load, without distinguishing between nonexistent and
      // nonvisible objects.
      return;
    }

    $capobj = PhabricatorPolicyCapability::getCapabilityByKey($capability);
    $rejection = null;
    if ($capobj) {
      $rejection = $capobj->describeCapabilityRejection();
      $capability_name = $capobj->getCapabilityName();
    } else {
      $capability_name = $capability;
    }

    if (!$rejection) {
      // We couldn't find the capability object, or it doesn't provide a
      // tailored rejection string.
      $rejection = pht(
        'You do not have the required capability ("%s") to do whatever you '.
        'are trying to do.',
        $capability);
    }

    $more = PhabricatorPolicy::getPolicyExplanation($this->viewer, $policy);
    $exceptions = $object->describeAutomaticCapability($capability);

    $details = array_filter(array_merge(array($more), (array)$exceptions));

    // NOTE: Not every type of policy object has a real PHID; just load an
    // empty handle if a real PHID isn't available.
    $phid = nonempty($object->getPHID(), PhabricatorPHIDConstants::PHID_VOID);

    $handle = id(new PhabricatorHandleQuery())
      ->setViewer($this->viewer)
      ->withPHIDs(array($phid))
      ->executeOne();

    $is_serious = PhabricatorEnv::getEnvConfig('phabricator.serious-business');
    if ($is_serious) {
      $title = pht(
        'Access Denied: %s',
        $handle->getObjectName());
    } else {
      $title = pht(
        'You Shall Not Pass: %s',
        $handle->getObjectName());
    }

    $full_message = pht(
      '[%s] (%s) %s // %s',
      $title,
      $capability_name,
      $rejection,
      implode(' ', $details));

    $exception = id(new PhabricatorPolicyException($full_message))
      ->setTitle($title)
      ->setRejection($rejection)
      ->setCapabilityName($capability_name)
      ->setMoreInfo($details);

    throw $exception;
  }

  private function loadCustomPolicies(array $phids) {
    $viewer = $this->viewer;
    $viewer_phid = $viewer->getPHID();

    $custom_policies = id(new PhabricatorPolicyQuery())
      ->setViewer($viewer)
      ->withPHIDs($phids)
      ->execute();
    $custom_policies = mpull($custom_policies, null, 'getPHID');


    $classes = array();
    $values = array();
    foreach ($custom_policies as $policy) {
      foreach ($policy->getCustomRuleClasses() as $class) {
        $classes[$class] = $class;
        $values[$class][] = $policy->getCustomRuleValues($class);
      }
    }

    foreach ($classes as $class => $ignored) {
      $object = newv($class, array());
      $object->willApplyRules($viewer, array_mergev($values[$class]));
      $classes[$class] = $object;
    }

    foreach ($custom_policies as $policy) {
      $policy->attachRuleObjects($classes);
    }

    if (empty($this->customPolicies[$viewer_phid])) {
      $this->customPolicies[$viewer_phid] = array();
    }

    $this->customPolicies[$viewer->getPHID()] += $custom_policies;
  }

  private function checkCustomPolicy($policy_phid) {
    $viewer = $this->viewer;
    $viewer_phid = $viewer->getPHID();

    $policy = $this->customPolicies[$viewer_phid][$policy_phid];

    $objects = $policy->getRuleObjects();
    $action = null;
    foreach ($policy->getRules() as $rule) {
      $object = idx($objects, idx($rule, 'rule'));
      if (!$object) {
        // Reject, this policy has a bogus rule.
        return false;
      }

      // If the user matches this rule, use this action.
      if ($object->applyRule($viewer, idx($rule, 'value'))) {
        $action = idx($rule, 'action');
        break;
      }
    }

    if ($action === null) {
      $action = $policy->getDefaultAction();
    }

    if ($action === PhabricatorPolicy::ACTION_ALLOW) {
      return true;
    }

    return false;
  }

  private function getObjectPolicy(
    PhabricatorPolicyInterface $object,
    $capability) {

    if ($this->forcedPolicy) {
      return $this->forcedPolicy;
    } else {
      return $object->getPolicy($capability);
    }
  }

}
