<?php

final class PhabricatorPolicy {

  private $phid;
  private $name;
  private $type;
  private $href;

  public static function newFromPolicyAndHandle(
    $policy_identifier,
    PhabricatorObjectHandle $handle = null) {

    $is_global = PhabricatorPolicyQuery::isGlobalPolicy($policy_identifier);
    if ($is_global) {
      return PhabricatorPolicyQuery::getGlobalPolicy($policy_identifier);
    }

    if (!$handle) {
      throw new Exception(
        "Policy identifier is an object PHID ('{$phid_identifier}'), but no ".
        "object handle was provided. A handle must be provided for object ".
        "policies.");
    }

    $handle_phid = $handle->getPHID();
    if ($policy_identifier != $handle_phid) {
      throw new Exception(
        "Policy identifier is an object PHID ('{$phid_identifier}'), but ".
        "the provided handle has a different PHID ('{$handle_phid}'). The ".
        "handle must correspond to the policy identifier.");
    }

    $policy = id(new PhabricatorPolicy())
      ->setPHID($policy_identifier)
      ->setHref($handle->getURI());

    $phid_type = phid_get_type($policy_identifier);
    switch ($phid_type) {
      case PhabricatorPHIDConstants::PHID_TYPE_PROJ:
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

  public function renderDescription() {
    if ($this->getHref()) {
      $desc = phutil_tag(
        'a',
        array(
          'href' => $this->getHref(),
        ),
        $this->getName());
    } else {
      $desc = $this->getName();
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
