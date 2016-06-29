<?php

final class PhabricatorPolicyQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $object;
  private $phids;

  const OBJECT_POLICY_PREFIX = 'obj.';

  public function setObject(PhabricatorPolicyInterface $object) {
    $this->object = $object;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public static function loadPolicies(
    PhabricatorUser $viewer,
    PhabricatorPolicyInterface $object) {

    $results = array();

    $map = array();
    foreach ($object->getCapabilities() as $capability) {
      $map[$capability] = $object->getPolicy($capability);
    }

    $policies = id(new PhabricatorPolicyQuery())
      ->setViewer($viewer)
      ->withPHIDs($map)
      ->execute();

    foreach ($map as $capability => $phid) {
      $results[$capability] = $policies[$phid];
    }

    return $results;
  }

  public static function renderPolicyDescriptions(
    PhabricatorUser $viewer,
    PhabricatorPolicyInterface $object,
    $icon = false) {

    $policies = self::loadPolicies($viewer, $object);

    foreach ($policies as $capability => $policy) {
      $policies[$capability] = $policy->renderDescription($icon);
    }

    return $policies;
  }

  protected function loadPage() {
    if ($this->object && $this->phids) {
      throw new Exception(
        pht(
          'You can not issue a policy query with both %s and %s.',
          'setObject()',
          'setPHIDs()'));
    } else if ($this->object) {
      $phids = $this->loadObjectPolicyPHIDs();
    } else {
      $phids = $this->phids;
    }

    $phids = array_fuse($phids);

    $results = array();

    // First, load global policies.
    foreach (self::getGlobalPolicies() as $phid => $policy) {
      if (isset($phids[$phid])) {
        $results[$phid] = $policy;
        unset($phids[$phid]);
      }
    }

    // Now, load object policies.
    foreach (self::getObjectPolicies($this->object) as $phid => $policy) {
      if (isset($phids[$phid])) {
        $results[$phid] = $policy;
        unset($phids[$phid]);
      }
    }

    // If we still need policies, we're going to have to fetch data. Bucket
    // the remaining policies into rule-based policies and handle-based
    // policies.
    if ($phids) {
      $rule_policies = array();
      $handle_policies = array();
      foreach ($phids as $phid) {
        $phid_type = phid_get_type($phid);
        if ($phid_type == PhabricatorPolicyPHIDTypePolicy::TYPECONST) {
          $rule_policies[$phid] = $phid;
        } else {
          $handle_policies[$phid] = $phid;
        }
      }

      if ($handle_policies) {
        $handles = id(new PhabricatorHandleQuery())
          ->setViewer($this->getViewer())
          ->withPHIDs($handle_policies)
          ->execute();
        foreach ($handle_policies as $phid) {
          $results[$phid] = PhabricatorPolicy::newFromPolicyAndHandle(
            $phid,
            $handles[$phid]);
        }
      }

      if ($rule_policies) {
        $rules = id(new PhabricatorPolicy())->loadAllWhere(
          'phid IN (%Ls)',
          $rule_policies);
        $results += mpull($rules, null, 'getPHID');
      }
    }

    $results = msort($results, 'getSortKey');

    return $results;
  }

  public static function isGlobalPolicy($policy) {
    $global_policies = self::getGlobalPolicies();

    if (isset($global_policies[$policy])) {
      return true;
    }

    return false;
  }

  public static function getGlobalPolicy($policy) {
    if (!self::isGlobalPolicy($policy)) {
      throw new Exception(pht("Policy '%s' is not a global policy!", $policy));
    }
    return idx(self::getGlobalPolicies(), $policy);
  }

  private static function getGlobalPolicies() {
    static $constants = array(
      PhabricatorPolicies::POLICY_PUBLIC,
      PhabricatorPolicies::POLICY_USER,
      PhabricatorPolicies::POLICY_ADMIN,
      PhabricatorPolicies::POLICY_NOONE,
    );

    $results = array();
    foreach ($constants as $constant) {
      $results[$constant] = id(new PhabricatorPolicy())
        ->setType(PhabricatorPolicyType::TYPE_GLOBAL)
        ->setPHID($constant)
        ->setName(self::getGlobalPolicyName($constant))
        ->setShortName(self::getGlobalPolicyShortName($constant))
        ->makeEphemeral();
    }

    return $results;
  }

  private static function getGlobalPolicyName($policy) {
    switch ($policy) {
      case PhabricatorPolicies::POLICY_PUBLIC:
        return pht('Public (No Login Required)');
      case PhabricatorPolicies::POLICY_USER:
        return pht('All Users');
      case PhabricatorPolicies::POLICY_ADMIN:
        return pht('Administrators');
      case PhabricatorPolicies::POLICY_NOONE:
        return pht('No One');
      default:
        return pht('Unknown Policy');
    }
  }

  private static function getGlobalPolicyShortName($policy) {
    switch ($policy) {
      case PhabricatorPolicies::POLICY_PUBLIC:
        return pht('Public');
      default:
        return null;
    }
  }

  private function loadObjectPolicyPHIDs() {
    $phids = array();
    $viewer = $this->getViewer();

    if ($viewer->getPHID()) {
      $pref_key = PhabricatorPolicyFavoritesSetting::SETTINGKEY;

      $favorite_limit = 10;
      $default_limit = 5;

      // If possible, show the user's 10 most recently used projects.
      $favorites = $viewer->getUserSetting($pref_key);
      if (!is_array($favorites)) {
        $favorites = array();
      }
      $favorite_phids = array_keys($favorites);
      $favorite_phids = array_slice($favorite_phids, -$favorite_limit);

      if ($favorite_phids) {
        $projects = id(new PhabricatorProjectQuery())
          ->setViewer($viewer)
          ->withPHIDs($favorite_phids)
          ->withIsMilestone(false)
          ->setLimit($favorite_limit)
          ->execute();
        $projects = mpull($projects, null, 'getPHID');
      } else {
        $projects = array();
      }

      // If we didn't find enough favorites, add some default projects. These
      // are just arbitrary projects that the viewer is a member of, but may
      // be useful on smaller installs and for new users until they can use
      // the control enough time to establish useful favorites.
      if (count($projects) < $default_limit) {
        $default_projects = id(new PhabricatorProjectQuery())
          ->setViewer($viewer)
          ->withMemberPHIDs(array($viewer->getPHID()))
          ->withIsMilestone(false)
          ->withStatuses(
            array(
              PhabricatorProjectStatus::STATUS_ACTIVE,
            ))
          ->setLimit($default_limit)
          ->execute();
        $default_projects = mpull($default_projects, null, 'getPHID');
        $projects = $projects + $default_projects;
        $projects = array_slice($projects, 0, $default_limit);
      }

      foreach ($projects as $project) {
        $phids[] = $project->getPHID();
      }

      // Include the "current viewer" policy. This improves consistency, but
      // is also useful for creating private instances of normally-shared object
      // types, like repositories.
      $phids[] = $viewer->getPHID();
    }

    $capabilities = $this->object->getCapabilities();
    foreach ($capabilities as $capability) {
      $policy = $this->object->getPolicy($capability);
      if (!$policy) {
        continue;
      }
      $phids[] = $policy;
    }

    // If this install doesn't have "Public" enabled, don't include it as an
    // option unless the object already has a "Public" policy. In this case we
    // retain the policy but enforce it as though it was "All Users".
    $show_public = PhabricatorEnv::getEnvConfig('policy.allow-public');
    foreach (self::getGlobalPolicies() as $phid => $policy) {
      if ($phid == PhabricatorPolicies::POLICY_PUBLIC) {
        if (!$show_public) {
          continue;
        }
      }
      $phids[] = $phid;
    }

    foreach (self::getObjectPolicies($this->object) as $phid => $policy) {
      $phids[] = $phid;
    }

    return $phids;
  }

  protected function shouldDisablePolicyFiltering() {
    // Policy filtering of policies is currently perilous and not required by
    // the application.
    return true;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorPolicyApplication';
  }

  public static function isSpecialPolicy($identifier) {
    if (self::isObjectPolicy($identifier)) {
      return true;
    }

    if (self::isGlobalPolicy($identifier)) {
      return true;
    }

    return false;
  }


/* -(  Object Policies  )---------------------------------------------------- */


  public static function isObjectPolicy($identifier) {
    $prefix = self::OBJECT_POLICY_PREFIX;
    return !strncmp($identifier, $prefix, strlen($prefix));
  }

  public static function getObjectPolicy($identifier) {
    if (!self::isObjectPolicy($identifier)) {
      return null;
    }

    $policies = self::getObjectPolicies(null);
    return idx($policies, $identifier);
  }

  public static function getObjectPolicyRule($identifier) {
    if (!self::isObjectPolicy($identifier)) {
      return null;
    }

    $rules = self::getObjectPolicyRules(null);
    return idx($rules, $identifier);
  }

  public static function getObjectPolicies($object) {
    $rule_map = self::getObjectPolicyRules($object);

    $results = array();
    foreach ($rule_map as $key => $rule) {
      $results[$key] = id(new PhabricatorPolicy())
        ->setType(PhabricatorPolicyType::TYPE_OBJECT)
        ->setPHID($key)
        ->setIcon($rule->getObjectPolicyIcon())
        ->setName($rule->getObjectPolicyName())
        ->setShortName($rule->getObjectPolicyShortName())
        ->makeEphemeral();
    }

    return $results;
  }

  public static function getObjectPolicyRules($object) {
    $rules = id(new PhutilClassMapQuery())
      ->setAncestorClass('PhabricatorPolicyRule')
      ->execute();

    $results = array();
    foreach ($rules as $rule) {
      $key = $rule->getObjectPolicyKey();
      if (!$key) {
        continue;
      }

      $full_key = $rule->getObjectPolicyFullKey();
      if (isset($results[$full_key])) {
        throw new Exception(
          pht(
            'Two policy rules (of classes "%s" and "%s") define the same '.
            'object policy key ("%s"), but each object policy rule must use '.
            'a unique key.',
            get_class($rule),
            get_class($results[$full_key]),
            $key));
      }

      $results[$full_key] = $rule;
    }

    if ($object !== null) {
      foreach ($results as $key => $rule) {
        if (!$rule->canApplyToObject($object)) {
          unset($results[$key]);
        }
      }
    }

    return $results;
  }

  public static function getDefaultPolicyForObject(
    PhabricatorUser $viewer,
    PhabricatorPolicyInterface $object,
    $capability) {

    $phid = $object->getPHID();
    if (!$phid) {
      return null;
    }

    $type = phid_get_type($phid);

    $map = self::getDefaultObjectTypePolicyMap();

    if (empty($map[$type][$capability])) {
      return null;
    }

    $policy_phid = $map[$type][$capability];

    return id(new PhabricatorPolicyQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($policy_phid))
      ->executeOne();
  }

  private static function getDefaultObjectTypePolicyMap() {
    static $map;

    if ($map === null) {
      $map = array();

      $apps = PhabricatorApplication::getAllApplications();
      foreach ($apps as $app) {
        $map += $app->getDefaultObjectTypePolicyMap();
      }
    }

    return $map;
  }


}
