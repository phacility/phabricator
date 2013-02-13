<?php

final class PhabricatorPolicy {

  private $phid;
  private $name;
  private $type;
  private $href;


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
