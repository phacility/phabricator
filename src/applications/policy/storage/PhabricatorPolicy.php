<?php

final class PhabricatorPolicy
  extends PhabricatorPolicyDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorDestructibleInterface {

  const ACTION_ALLOW = 'allow';
  const ACTION_DENY = 'deny';

  private $name;
  private $shortName;
  private $type;
  private $href;
  private $workflow;
  private $icon;

  protected $rules = array();
  protected $defaultAction = self::ACTION_DENY;

  private $ruleObjects = self::ATTACHABLE;

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'rules' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'defaultAction' => 'text32',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_phid' => null,
        'phid' => array(
          'columns' => array('phid'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorPolicyPHIDTypePolicy::TYPECONST);
  }

  public static function newFromPolicyAndHandle(
    $policy_identifier,
    PhabricatorObjectHandle $handle = null) {

    $is_global = PhabricatorPolicyQuery::isGlobalPolicy($policy_identifier);
    if ($is_global) {
      return PhabricatorPolicyQuery::getGlobalPolicy($policy_identifier);
    }

    $policy = PhabricatorPolicyQuery::getObjectPolicy($policy_identifier);
    if ($policy) {
      return $policy;
    }

    if (!$handle) {
      throw new Exception(
        pht(
          "Policy identifier is an object PHID ('%s'), but no object handle ".
          "was provided. A handle must be provided for object policies.",
          $policy_identifier));
    }

    $handle_phid = $handle->getPHID();
    if ($policy_identifier != $handle_phid) {
      throw new Exception(
        pht(
          "Policy identifier is an object PHID ('%s'), but the provided ".
          "handle has a different PHID ('%s'). The handle must correspond ".
          "to the policy identifier.",
          $policy_identifier,
          $handle_phid));
    }

    $policy = id(new PhabricatorPolicy())
      ->setPHID($policy_identifier)
      ->setHref($handle->getURI());

    $phid_type = phid_get_type($policy_identifier);
    switch ($phid_type) {
      case PhabricatorProjectProjectPHIDType::TYPECONST:
        $policy->setType(PhabricatorPolicyType::TYPE_PROJECT);
        $policy->setName($handle->getName());
        break;
      case PhabricatorPeopleUserPHIDType::TYPECONST:
        $policy->setType(PhabricatorPolicyType::TYPE_USER);
        $policy->setName($handle->getFullName());
        break;
      case PhabricatorPolicyPHIDTypePolicy::TYPECONST:
        // TODO: This creates a weird handle-based version of a rule policy.
        // It behaves correctly, but can't be applied since it doesn't have
        // any rules. It is used to render transactions, and might need some
        // cleanup.
        break;
      default:
        $policy->setType(PhabricatorPolicyType::TYPE_MASKED);
        $policy->setName($handle->getFullName());
        break;
    }

    $policy->makeEphemeral();

    return $policy;
  }

  public function setType($type) {
    $this->type = $type;
    return $this;
  }

  public function getType() {
    if (!$this->type) {
      return PhabricatorPolicyType::TYPE_CUSTOM;
    }
    return $this->type;
  }

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getName() {
    if (!$this->name) {
      return pht('Custom Policy');
    }
    return $this->name;
  }

  public function setShortName($short_name) {
    $this->shortName = $short_name;
    return $this;
  }

  public function getShortName() {
    if ($this->shortName) {
      return $this->shortName;
    }
    return $this->getName();
  }

  public function setHref($href) {
    $this->href = $href;
    return $this;
  }

  public function getHref() {
    return $this->href;
  }

  public function setWorkflow($workflow) {
    $this->workflow = $workflow;
    return $this;
  }

  public function getWorkflow() {
    return $this->workflow;
  }

  public function setIcon($icon) {
    $this->icon = $icon;
    return $this;
  }

  public function getIcon() {
    if ($this->icon) {
      return $this->icon;
    }

    switch ($this->getType()) {
      case PhabricatorPolicyType::TYPE_GLOBAL:
        static $map = array(
          PhabricatorPolicies::POLICY_PUBLIC  => 'fa-globe',
          PhabricatorPolicies::POLICY_USER    => 'fa-users',
          PhabricatorPolicies::POLICY_ADMIN   => 'fa-eye',
          PhabricatorPolicies::POLICY_NOONE   => 'fa-ban',
        );
        return idx($map, $this->getPHID(), 'fa-question-circle');
      case PhabricatorPolicyType::TYPE_USER:
        return 'fa-user';
      case PhabricatorPolicyType::TYPE_PROJECT:
        return 'fa-briefcase';
      case PhabricatorPolicyType::TYPE_CUSTOM:
      case PhabricatorPolicyType::TYPE_MASKED:
        return 'fa-certificate';
      default:
        return 'fa-question-circle';
    }
  }

  public function getSortKey() {
    return sprintf(
      '%02d%s',
      PhabricatorPolicyType::getPolicyTypeOrder($this->getType()),
      $this->getSortName());
  }

  private function getSortName() {
    if ($this->getType() == PhabricatorPolicyType::TYPE_GLOBAL) {
      static $map = array(
        PhabricatorPolicies::POLICY_PUBLIC  => 0,
        PhabricatorPolicies::POLICY_USER    => 1,
        PhabricatorPolicies::POLICY_ADMIN   => 2,
        PhabricatorPolicies::POLICY_NOONE   => 3,
      );
      return idx($map, $this->getPHID());
    }
    return $this->getName();
  }

  public static function getPolicyExplanation(
    PhabricatorUser $viewer,
    $policy) {

    $rule = PhabricatorPolicyQuery::getObjectPolicyRule($policy);
    if ($rule) {
      return $rule->getPolicyExplanation();
    }

    switch ($policy) {
      case PhabricatorPolicies::POLICY_PUBLIC:
        return pht('This object is public.');
      case PhabricatorPolicies::POLICY_USER:
        return pht('Logged in users can take this action.');
      case PhabricatorPolicies::POLICY_ADMIN:
        return pht('Administrators can take this action.');
      case PhabricatorPolicies::POLICY_NOONE:
        return pht('By default, no one can take this action.');
      default:
        $handle = id(new PhabricatorHandleQuery())
          ->setViewer($viewer)
          ->withPHIDs(array($policy))
          ->executeOne();

        $type = phid_get_type($policy);
        if ($type == PhabricatorProjectProjectPHIDType::TYPECONST) {
          return pht(
            'Members of the project "%s" can take this action.',
            $handle->getFullName());
        } else if ($type == PhabricatorPeopleUserPHIDType::TYPECONST) {
          return pht(
            '%s can take this action.',
            $handle->getFullName());
        } else if ($type == PhabricatorPolicyPHIDTypePolicy::TYPECONST) {
          return pht(
            'This object has a custom policy controlling who can take this '.
            'action.');
        } else {
          return pht(
            'This object has an unknown or invalid policy setting ("%s").',
            $policy);
        }
    }
  }

  public function getFullName() {
    switch ($this->getType()) {
      case PhabricatorPolicyType::TYPE_PROJECT:
        return pht('Project: %s', $this->getName());
      case PhabricatorPolicyType::TYPE_MASKED:
        return pht('Other: %s', $this->getName());
      default:
        return $this->getName();
    }
  }

  public function renderDescription($icon = false) {
    $img = null;
    if ($icon) {
      $img = id(new PHUIIconView())
        ->setIcon($this->getIcon());
    }

    if ($this->getHref()) {
      $desc = javelin_tag(
        'a',
        array(
          'href' => $this->getHref(),
          'class' => 'policy-link',
          'sigil' => $this->getWorkflow() ? 'workflow' : null,
        ),
        array(
          $img,
          $this->getName(),
        ));
    } else {
      if ($img) {
        $desc = array($img, $this->getName());
      } else {
        $desc = $this->getName();
      }
    }

    switch ($this->getType()) {
      case PhabricatorPolicyType::TYPE_PROJECT:
        return pht('%s (Project)', $desc);
      case PhabricatorPolicyType::TYPE_CUSTOM:
        return $desc;
      case PhabricatorPolicyType::TYPE_MASKED:
        return pht(
          '%s (You do not have permission to view policy details.)',
          $desc);
      default:
        return $desc;
    }
  }

  /**
   * Return a list of custom rule classes (concrete subclasses of
   * @{class:PhabricatorPolicyRule}) this policy uses.
   *
   * @return list<string> List of class names.
   */
  public function getCustomRuleClasses() {
    $classes = array();

    foreach ($this->getRules() as $rule) {
      if (!is_array($rule)) {
        // This rule is invalid. We'll reject it later, but don't need to
        // extract anything from it for now.
        continue;
      }

      $class = idx($rule, 'rule');
      try {
        if (class_exists($class)) {
          $classes[$class] = $class;
        }
      } catch (Exception $ex) {
        continue;
      }
    }

    return array_keys($classes);
  }

  /**
   * Return a list of all values used by a given rule class to implement this
   * policy. This is used to bulk load data (like project memberships) in order
   * to apply policy filters efficiently.
   *
   * @param string Policy rule classname.
   * @return list<wild> List of values used in this policy.
   */
  public function getCustomRuleValues($rule_class) {
    $values = array();
    foreach ($this->getRules() as $rule) {
      if ($rule['rule'] == $rule_class) {
        $values[] = $rule['value'];
      }
    }
    return $values;
  }

  public function attachRuleObjects(array $objects) {
    $this->ruleObjects = $objects;
    return $this;
  }

  public function getRuleObjects() {
    return $this->assertAttached($this->ruleObjects);
  }


  /**
   * Return `true` if this policy is stronger (more restrictive) than some
   * other policy.
   *
   * Because policies are complicated, determining which policies are
   * "stronger" is not trivial. This method uses a very coarse working
   * definition of policy strength which is cheap to compute, unambiguous,
   * and intuitive in the common cases.
   *
   * This method returns `true` if the //class// of this policy is stronger
   * than the other policy, even if the policies are (or might be) the same in
   * practice. For example, "Members of Project X" is considered a stronger
   * policy than "All Users", even though "Project X" might (in some rare
   * cases) contain every user.
   *
   * Generally, the ordering here is:
   *
   *   - Public
   *   - All Users
   *   - (Everything Else)
   *   - No One
   *
   * In the "everything else" bucket, we can't make any broad claims about
   * which policy is stronger (and we especially can't make those claims
   * cheaply).
   *
   * Even if we fully evaluated each policy, the two policies might be
   * "Members of X" and "Members of Y", each of which permits access to some
   * set of unique users. In this case, neither is strictly stronger than
   * the other.
   *
   * @param PhabricatorPolicy Other policy.
   * @return bool `true` if this policy is more restrictive than the other
   *  policy.
   */
  public function isStrongerThan(PhabricatorPolicy $other) {
    $this_policy = $this->getPHID();
    $other_policy = $other->getPHID();

    $strengths = array(
      PhabricatorPolicies::POLICY_PUBLIC => -2,
      PhabricatorPolicies::POLICY_USER => -1,
      // (Default policies have strength 0.)
      PhabricatorPolicies::POLICY_NOONE => 1,
    );

    $this_strength = idx($strengths, $this->getPHID(), 0);
    $other_strength = idx($strengths, $other->getPHID(), 0);

    return ($this_strength > $other_strength);
  }



/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    // NOTE: We implement policies only so we can comply with the interface.
    // The actual query skips them, as enforcing policies on policies seems
    // perilous and isn't currently required by the application.
    return PhabricatorPolicies::POLICY_PUBLIC;
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }

  public function describeAutomaticCapability($capability) {
    return null;
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    $this->delete();
  }


}
