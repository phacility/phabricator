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
        if ($type == PhabricatorProjectPHIDTypeProject::TYPECONST) {
          $need_projects[$policy] = $policy;
        }
      }
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
        if ($type == PhabricatorProjectPHIDTypeProject::TYPECONST) {
          if (!empty($this->userProjects[$viewer->getPHID()][$policy])) {
            return true;
          } else {
            $this->rejectObject($object, $policy, $capability);
          }
        } else if ($type == PhabricatorPeoplePHIDTypeUser::TYPECONST) {
          if ($viewer->getPHID() == $policy) {
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

  private function rejectImpossiblePolicy(
    PhabricatorPolicyInterface $object,
    $policy,
    $capability) {

    if (!$this->raisePolicyExceptions) {
      return;
    }

    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        $message = pht("This object has an impossible view policy.");
        break;
      case PhabricatorPolicyCapability::CAN_EDIT:
        $message = pht("This object has an impossible edit policy.");
        break;
      case PhabricatorPolicyCapability::CAN_JOIN:
        $message = pht("This object has an impossible join policy.");
        break;
      default:
        $message = pht("This object has an impossible policy.");
        break;
    }

    throw new PhabricatorPolicyException($message);
  }

  public function rejectObject(
    PhabricatorPolicyInterface $object,
    $policy,
    $capability) {

    if (!$this->raisePolicyExceptions) {
      return;
    }

    $more = array();
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        $message = pht(
          'This object exists, but you do not have permission to view it.');
        break;
      case PhabricatorPolicyCapability::CAN_EDIT:
        $message = pht('You do not have permission to edit this object.');
        break;
      case PhabricatorPolicyCapability::CAN_JOIN:
        $message = pht('You do not have permission to join this object.');
        break;
      default:
        // TODO: Farm these out to applications?
        $message = pht(
          'You do not have a required capability ("%s") to do whatever you '.
          'are trying to do.',
          $capability);
        break;
    }

    switch ($policy) {
      case PhabricatorPolicies::POLICY_PUBLIC:
        // Presumably, this is a bug, so we don't bother specializing the
        // strings.
        $more = pht('This object is public.');
        break;
      case PhabricatorPolicies::POLICY_USER:
        // We always raise this as "log in", so we don't need to specialize.
        $more = pht('This object is available to logged in users.');
        break;
      case PhabricatorPolicies::POLICY_ADMIN:
        switch ($capability) {
          case PhabricatorPolicyCapability::CAN_VIEW:
            $more = pht('Administrators can view this object.');
            break;
          case PhabricatorPolicyCapability::CAN_EDIT:
            $more = pht('Administrators can edit this object.');
            break;
          case PhabricatorPolicyCapability::CAN_JOIN:
            $more = pht('Administrators can join this object.');
            break;
        }
        break;
      case PhabricatorPolicies::POLICY_NOONE:
        switch ($capability) {
          case PhabricatorPolicyCapability::CAN_VIEW:
            $more = pht('By default, no one can view this object.');
            break;
          case PhabricatorPolicyCapability::CAN_EDIT:
            $more = pht('By default, no one can edit this object.');
            break;
          case PhabricatorPolicyCapability::CAN_JOIN:
            $more = pht('By default, no one can join this object.');
            break;
        }
        break;
      default:
        $handle = id(new PhabricatorHandleQuery())
          ->setViewer($this->viewer)
          ->withPHIDs(array($policy))
          ->executeOne();

        $type = phid_get_type($policy);
        if ($type == PhabricatorProjectPHIDTypeProject::TYPECONST) {
          switch ($capability) {
            case PhabricatorPolicyCapability::CAN_VIEW:
              $more = pht(
                'This object is visible to members of the project "%s".',
                $handle->getFullName());
              break;
            case PhabricatorPolicyCapability::CAN_EDIT:
              $more = pht(
                'This object can be edited by members of the project "%s".',
                $handle->getFullName());
              break;
            case PhabricatorPolicyCapability::CAN_JOIN:
              $more = pht(
                'This object can be joined by members of the project "%s".',
                $handle->getFullName());
              break;
          }
        } else if ($type == PhabricatorPeoplePHIDTypeUser::TYPECONST) {
          switch ($capability) {
            case PhabricatorPolicyCapability::CAN_VIEW:
              $more = pht(
                '%s can view this object.',
                $handle->getFullName());
              break;
            case PhabricatorPolicyCapability::CAN_EDIT:
              $more = pht(
                '%s can edit this object.',
                $handle->getFullName());
              break;
            case PhabricatorPolicyCapability::CAN_JOIN:
              $more = pht(
                '%s can join this object.',
                $handle->getFullName());
              break;
          }
        } else {
          $more = pht("This object has an unknown or invalid policy setting.");
        }
        break;
    }

    $more = array_merge(
      array_filter(array($more)),
      array_filter((array)$object->describeAutomaticCapability($capability)));

    $exception = new PhabricatorPolicyException($message);
    $exception->setMoreInfo($more);

    throw $exception;
  }
}
