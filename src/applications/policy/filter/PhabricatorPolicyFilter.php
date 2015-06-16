<?php

final class PhabricatorPolicyFilter extends Phobject {

  private $viewer;
  private $objects;
  private $capabilities;
  private $raisePolicyExceptions;
  private $userProjects;
  private $customPolicies = array();
  private $objectPolicies = array();
  private $forcedPolicy;

  public static function mustRetainCapability(
    PhabricatorUser $user,
    PhabricatorPolicyInterface $object,
    $capability) {

    if (!self::hasCapability($user, $object, $capability)) {
      throw new Exception(
        pht(
          "You can not make that edit, because it would remove your ability ".
          "to '%s' the object.",
          $capability));
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
      throw new PhutilInvalidStateException('setViewer', 'requireCapabilities');
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
    $need_objpolicies = array();
    foreach ($objects as $key => $object) {
      $object_capabilities = $object->getCapabilities();
      foreach ($capabilities as $capability) {
        if (!in_array($capability, $object_capabilities)) {
          throw new Exception(
            pht(
              "Testing for capability '%s' on an object which does ".
              "not have that capability!",
              $capability));
        }

        $policy = $this->getObjectPolicy($object, $capability);

        if (PhabricatorPolicyQuery::isObjectPolicy($policy)) {
          $need_objpolicies[$policy][] = $object;
          continue;
        }

        $type = phid_get_type($policy);
        if ($type == PhabricatorProjectProjectPHIDType::TYPECONST) {
          $need_projects[$policy] = $policy;
          continue;
        }

        if ($type == PhabricatorPolicyPHIDTypePolicy::TYPECONST) {
          $need_policies[$policy][] = $object;
          continue;
        }
      }
    }

    if ($need_objpolicies) {
      $this->loadObjectPolicies($need_objpolicies);
    }

    if ($need_policies) {
      $this->loadCustomPolicies($need_policies);
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
      foreach ($capabilities as $capability) {
        if (!$this->checkCapability($object, $capability)) {
          // If we're missing any capability, move on to the next object.
          continue 2;
        }
      }

      // If we make it here, we have all of the required capabilities.
      $filtered[$key] = $object;
    }

    // If we survied the primary checks, apply extended checks to objects
    // with extended policies.
    $results = array();
    $extended = array();
    foreach ($filtered as $key => $object) {
      if ($object instanceof PhabricatorExtendedPolicyInterface) {
        $extended[$key] = $object;
      } else {
        $results[$key] = $object;
      }
    }

    if ($extended) {
      $results += $this->applyExtendedPolicyChecks($extended);
      // Put results back in the original order.
      $results = array_select_keys($results, array_keys($filtered));
    }

    return $results;
  }

  private function applyExtendedPolicyChecks(array $extended_objects) {
    // First, we're going to detect cycles and reject any objects which are
    // part of a cycle. We don't want to loop forever if an object has a
    // self-referential or nonsense policy.

    static $in_flight = array();

    $all_phids = array();
    foreach ($extended_objects as $key => $object) {
      $phid = $object->getPHID();
      if (isset($in_flight[$phid])) {
        // TODO: This could be more user-friendly.
        $this->rejectObject($extended_objects[$key], false, '<cycle>');
        unset($extended_objects[$key]);
        continue;
      }

      // We might throw from rejectObject(), so we don't want to actually mark
      // anything as in-flight until we survive this entire step.
      $all_phids[$phid] = $phid;
    }

    foreach ($all_phids as $phid) {
      $in_flight[$phid] = true;
    }

    $caught = null;
    try {
      $extended_objects = $this->executeExtendedPolicyChecks($extended_objects);
    } catch (Exception $ex) {
      $caught = $ex;
    }

    foreach ($all_phids as $phid) {
      unset($in_flight[$phid]);
    }

    if ($caught) {
      throw $caught;
    }

    return $extended_objects;
  }

  private function executeExtendedPolicyChecks(array $extended_objects) {
    $viewer = $this->viewer;
    $filter_capabilities = $this->capabilities;

    // Iterate over the objects we need to filter and pull all the nonempty
    // policies into a flat, structured list.
    $all_structs = array();
    foreach ($extended_objects as $key => $extended_object) {
      foreach ($filter_capabilities as $extended_capability) {
        $extended_policies = $extended_object->getExtendedPolicy(
          $extended_capability,
          $viewer);
        if (!$extended_policies) {
          continue;
        }

        foreach ($extended_policies as $extended_policy) {
          list($object, $capabilities) = $extended_policy;

          // Build a description of the capabilities we need to check. This
          // will be something like `"view"`, or `"edit view"`, or possibly
          // a longer string with custom capabilities. Later, group the objects
          // up into groups which need the same capabilities tested.
          $capabilities = (array)$capabilities;
          $capabilities = array_fuse($capabilities);
          ksort($capabilities);
          $group = implode(' ', $capabilities);

          $struct = array(
            'key' => $key,
            'for' => $extended_capability,
            'object' => $object,
            'capabilities' => $capabilities,
            'group' => $group,
          );

          $all_structs[] = $struct;
        }
      }
    }

    // Extract any bare PHIDs from the structs; we need to load these objects.
    // These are objects which are required in order to perform an extended
    // policy check but which the original viewer did not have permission to
    // see (they presumably had other permissions which let them load the
    // object in the first place).
    $all_phids = array();
    foreach ($all_structs as $idx => $struct) {
      $object = $struct['object'];
      if (is_string($object)) {
        $all_phids[$object] = $object;
      }
    }

    // If we have some bare PHIDs, we need to load the corresponding objects.
    if ($all_phids) {
      // We can pull these with the omnipotent user because we're immediately
      // filtering them.
      $ref_objects = id(new PhabricatorObjectQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->withPHIDs($all_phids)
        ->execute();
      $ref_objects = mpull($ref_objects, null, 'getPHID');
    } else {
      $ref_objects = array();
    }

    // Group the list of checks by the capabilities we need to check.
    $groups = igroup($all_structs, 'group');
    foreach ($groups as $structs) {
      $head = head($structs);

      // All of the items in each group are checking for the same capabilities.
      $capabilities = $head['capabilities'];

      $key_map = array();
      $objects_in = array();
      foreach ($structs as $struct) {
        $extended_key = $struct['key'];
        if (empty($extended_objects[$key])) {
          // If this object has already been rejected by an earlier filtering
          // pass, we don't need to do any tests on it.
          continue;
        }

        $object = $struct['object'];
        if (is_string($object)) {
          // This is really a PHID, so look it up.
          $object_phid = $object;
          if (empty($ref_objects[$object_phid])) {
            // We weren't able to load the corresponding object, so just
            // reject this result outright.

            $reject = $extended_objects[$key];
            unset($extended_objects[$key]);

            // TODO: This could be friendlier.
            $this->rejectObject($reject, false, '<bad-ref>');
            continue;
          }
          $object = $ref_objects[$object_phid];
        }

        $phid = $object->getPHID();

        $key_map[$phid][] = $extended_key;
        $objects_in[$phid] = $object;
      }

      if ($objects_in) {
        $objects_out = id(new PhabricatorPolicyFilter())
          ->setViewer($viewer)
          ->requireCapabilities($capabilities)
          ->apply($objects_in);
        $objects_out = mpull($objects_out, null, 'getPHID');
      } else {
        $objects_out = array();
      }

      // If any objects were removed by filtering, we're going to reject all
      // of the original objects which needed them.
      foreach ($objects_in as $phid => $object_in) {
        if (isset($objects_out[$phid])) {
          // This object survived filtering, so we don't need to throw any
          // results away.
          continue;
        }

        foreach ($key_map[$phid] as $extended_key) {
          if (empty($extended_objects[$extended_key])) {
            // We've already rejected this object, so we don't need to reject
            // it again.
            continue;
          }

          $reject = $extended_objects[$extended_key];
          unset($extended_objects[$extended_key]);

          // TODO: This isn't as user-friendly as it could be. It's possible
          // that we're rejecting this object for multiple capability/policy
          // failures, though.
          $this->rejectObject($reject, false, '<extended>');
        }
      }
    }

    return $extended_objects;
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

    if ($object instanceof PhabricatorSpacesInterface) {
      $space_phid = $object->getSpacePHID();
      if (!$this->canViewerSeeObjectsInSpace($viewer, $space_phid)) {
        $this->rejectObjectFromSpace($object, $space_phid);
        return false;
      }
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
        if (PhabricatorPolicyQuery::isObjectPolicy($policy)) {
          if ($this->checkObjectPolicy($policy, $object)) {
            return true;
          } else {
            $this->rejectObject($object, $policy, $capability);
            break;
          }
        }

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
          if ($this->checkCustomPolicy($policy, $object)) {
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

    $access_denied = $this->renderAccessDenied($object);

    $full_message = pht(
      '[%s] (%s) %s // %s',
      $access_denied,
      $capability_name,
      $rejection,
      implode(' ', $details));

    $exception = id(new PhabricatorPolicyException($full_message))
      ->setTitle($access_denied)
      ->setRejection($rejection)
      ->setCapabilityName($capability_name)
      ->setMoreInfo($details);

    throw $exception;
  }

  private function loadObjectPolicies(array $map) {
    $viewer = $this->viewer;
    $viewer_phid = $viewer->getPHID();

    $rules = PhabricatorPolicyQuery::getObjectPolicyRules(null);

    $results = array();
    foreach ($map as $key => $object_list) {
      $rule = idx($rules, $key);
      if (!$rule) {
        continue;
      }

      foreach ($object_list as $object_key => $object) {
        if (!$rule->canApplyToObject($object)) {
          unset($object_list[$object_key]);
        }
      }

      $rule->willApplyRules($viewer, array(), $object_list);
      $results[$key] = $rule;
    }

    $this->objectPolicies[$viewer_phid] = $results;
  }

  private function loadCustomPolicies(array $map) {
    $viewer = $this->viewer;
    $viewer_phid = $viewer->getPHID();

    $custom_policies = id(new PhabricatorPolicyQuery())
      ->setViewer($viewer)
      ->withPHIDs(array_keys($map))
      ->execute();
    $custom_policies = mpull($custom_policies, null, 'getPHID');

    $classes = array();
    $values = array();
    $objects = array();
    foreach ($custom_policies as $policy_phid => $policy) {
      foreach ($policy->getCustomRuleClasses() as $class) {
        $classes[$class] = $class;
        $values[$class][] = $policy->getCustomRuleValues($class);

        foreach (idx($map, $policy_phid, array()) as $object) {
          $objects[$class][] = $object;
        }
      }
    }

    foreach ($classes as $class => $ignored) {
      $rule_object = newv($class, array());

      // Filter out any objects which the rule can't apply to.
      $target_objects = idx($objects, $class, array());
      foreach ($target_objects as $key => $target_object) {
        if (!$rule_object->canApplyToObject($target_object)) {
          unset($target_objects[$key]);
        }
      }

      $rule_object->willApplyRules(
        $viewer,
        array_mergev($values[$class]),
        $target_objects);

      $classes[$class] = $rule_object;
    }

    foreach ($custom_policies as $policy) {
      $policy->attachRuleObjects($classes);
    }

    if (empty($this->customPolicies[$viewer_phid])) {
      $this->customPolicies[$viewer_phid] = array();
    }

    $this->customPolicies[$viewer->getPHID()] += $custom_policies;
  }

  private function checkObjectPolicy(
    $policy_phid,
    PhabricatorPolicyInterface $object) {
    $viewer = $this->viewer;
    $viewer_phid = $viewer->getPHID();

    $rule = idx($this->objectPolicies[$viewer_phid], $policy_phid);
    if (!$rule) {
      return false;
    }

    if (!$rule->canApplyToObject($object)) {
      return false;
    }

    return $rule->applyRule($viewer, null, $object);
  }

  private function checkCustomPolicy(
    $policy_phid,
    PhabricatorPolicyInterface $object) {

    $viewer = $this->viewer;
    $viewer_phid = $viewer->getPHID();

    $policy = idx($this->customPolicies[$viewer_phid], $policy_phid);
    if (!$policy) {
      // Reject, this policy is bogus.
      return false;
    }

    $objects = $policy->getRuleObjects();
    $action = null;
    foreach ($policy->getRules() as $rule) {
      $rule_object = idx($objects, idx($rule, 'rule'));
      if (!$rule_object) {
        // Reject, this policy has a bogus rule.
        return false;
      }

      if (!$rule_object->canApplyToObject($object)) {
        // Reject, this policy rule can't be applied to the given object.
        return false;
      }

      // If the user matches this rule, use this action.
      if ($rule_object->applyRule($viewer, idx($rule, 'value'), $object)) {
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

  private function renderAccessDenied(PhabricatorPolicyInterface $object) {
    // NOTE: Not every type of policy object has a real PHID; just load an
    // empty handle if a real PHID isn't available.
    $phid = nonempty($object->getPHID(), PhabricatorPHIDConstants::PHID_VOID);

    $handle = id(new PhabricatorHandleQuery())
      ->setViewer($this->viewer)
      ->withPHIDs(array($phid))
      ->executeOne();

    $object_name = $handle->getObjectName();

    $is_serious = PhabricatorEnv::getEnvConfig('phabricator.serious-business');
    if ($is_serious) {
      $access_denied = pht(
        'Access Denied: %s',
        $object_name);
    } else {
      $access_denied = pht(
        'You Shall Not Pass: %s',
        $object_name);
    }

    return $access_denied;
  }


  private function canViewerSeeObjectsInSpace(
    PhabricatorUser $viewer,
    $space_phid) {

    $spaces = PhabricatorSpacesNamespaceQuery::getAllSpaces();

    // If there are no spaces, everything exists in an implicit default space
    // with no policy controls. This is the default state.
    if (!$spaces) {
      if ($space_phid !== null) {
        return false;
      } else {
        return true;
      }
    }

    if ($space_phid === null) {
      $space = PhabricatorSpacesNamespaceQuery::getDefaultSpace();
    } else {
      $space = idx($spaces, $space_phid);
    }

    if (!$space) {
      return false;
    }

    // This may be more involved later, but for now being able to see the
    // space is equivalent to being able to see everything in it.
    return self::hasCapability(
      $viewer,
      $space,
      PhabricatorPolicyCapability::CAN_VIEW);
  }

  private function rejectObjectFromSpace(
    PhabricatorPolicyInterface $object,
    $space_phid) {

    if (!$this->raisePolicyExceptions) {
      return;
    }

    if ($this->viewer->isOmnipotent()) {
      return;
    }

    $access_denied = $this->renderAccessDenied($object);

    $rejection = pht(
      'This object is in a space you do not have permission to access.');
    $full_message = pht('[%s] %s', $access_denied, $rejection);

    $exception = id(new PhabricatorPolicyException($full_message))
      ->setTitle($access_denied)
      ->setRejection($rejection);

    throw $exception;
  }

}
