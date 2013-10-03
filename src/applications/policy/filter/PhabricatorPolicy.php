<?php

final class PhabricatorPolicy {

  private $phid;
  private $name;
  private $type;
  private $href;
  private $icon;

  public static function newFromPolicyAndHandle(
    $policy_identifier,
    PhabricatorObjectHandle $handle = null) {

    $is_global = PhabricatorPolicyQuery::isGlobalPolicy($policy_identifier);
    if ($is_global) {
      return PhabricatorPolicyQuery::getGlobalPolicy($policy_identifier);
    }

    if (!$handle) {
      throw new Exception(
        "Policy identifier is an object PHID ('{$policy_identifier}'), but no ".
        "object handle was provided. A handle must be provided for object ".
        "policies.");
    }

    $handle_phid = $handle->getPHID();
    if ($policy_identifier != $handle_phid) {
      throw new Exception(
        "Policy identifier is an object PHID ('{$policy_identifier}'), but ".
        "the provided handle has a different PHID ('{$handle_phid}'). The ".
        "handle must correspond to the policy identifier.");
    }

    $policy = id(new PhabricatorPolicy())
      ->setPHID($policy_identifier)
      ->setHref($handle->getURI());

    $phid_type = phid_get_type($policy_identifier);
    switch ($phid_type) {
      case PhabricatorProjectPHIDTypeProject::TYPECONST:
        $policy->setType(PhabricatorPolicyType::TYPE_PROJECT);
        $policy->setName($handle->getName());
        break;
      default:
        $policy->setType(PhabricatorPolicyType::TYPE_MASKED);
        $policy->setName($handle->getFullName());
        break;
    }

    return $policy;
  }

  public function setType($type) {
    $this->type = $type;
    return $this;
  }

  public function getType() {
    return $this->type;
  }

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getName() {
    return $this->name;
  }

  public function setPHID($phid) {
    $this->phid = $phid;
    return $this;
  }

  public function getPHID() {
    return $this->phid;
  }

  public function setHref($href) {
    $this->href = $href;
    return $this;
  }

  public function getHref() {
    return $this->href;
  }

  public function getIcon() {
    switch ($this->getType()) {
      case PhabricatorPolicyType::TYPE_GLOBAL:
        static $map = array(
          PhabricatorPolicies::POLICY_PUBLIC  => 'policy-public',
          PhabricatorPolicies::POLICY_USER    => 'policy-all',
          PhabricatorPolicies::POLICY_ADMIN   => 'policy-admin',
          PhabricatorPolicies::POLICY_NOONE   => 'policy-noone',
        );
        return idx($map, $this->getPHID(), 'policy-unknown');
      break;
      case PhabricatorPolicyType::TYPE_PROJECT:
        return 'policy-project';
      break;
      case PhabricatorPolicyType::TYPE_MASKED:
        return 'policy-custom';
      break;
      default:
        return 'policy-unknown';
      break;
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

  public function getExplanation($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        switch ($this->getPHID()) {
          case PhabricatorPolicies::POLICY_PUBLIC:
            return pht('Visible to the entire internet.');
          case PhabricatorPolicies::POLICY_USER:
            return pht('Visible to all logged in users.');
          case PhabricatorPolicies::POLICY_ADMIN:
            return pht('Visible to all administrators.');
          case PhabricatorPolicies::POLICY_NOONE:
            return pht('Not visible to anyone by default.');
        }

        switch ($this->getType()) {
          case PhabricatorPolicyType::TYPE_PROJECT:
            return pht(
              'Visible to members of the project "%s".',
              $this->getName());
          case PhabricatorPolicyType::TYPE_MASKED:
            return pht('Other: %s', $this->getName());
        }
        break;
      case PhabricatorPolicyCapability::CAN_EDIT:
        switch ($this->getPHID()) {
          case PhabricatorPolicies::POLICY_USER:
            return pht('Editable by all logged in users.');
          case PhabricatorPolicies::POLICY_ADMIN:
            return pht('Editable by all administrators.');
          case PhabricatorPolicies::POLICY_NOONE:
            return pht('Not editable by default.');
        }

        switch ($this->getType()) {
          case PhabricatorPolicyType::TYPE_PROJECT:
            return pht(
              'Editable by members of the project "%s".',
              $this->getName());
          case PhabricatorPolicyType::TYPE_MASKED:
            return pht('Other: %s', $this->getName());
        }
        break;
    }


    return pht('?');
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

  public function renderDescription($icon=false) {
    $img = null;
    if ($icon) {
      $img = id(new PHUIIconView())
        ->setSpriteSheet(PHUIIconView::SPRITE_STATUS)
        ->setSpriteIcon($this->getIcon());
    }

    if ($this->getHref()) {
      $desc = phutil_tag(
        'a',
        array(
          'href' => $this->getHref(),
          'class' => 'policy-link',
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
      case PhabricatorPolicyType::TYPE_MASKED:
        return pht(
          '%s (You do not have permission to view policy details.)',
          $desc);
      default:
        return $desc;
    }
  }
}
