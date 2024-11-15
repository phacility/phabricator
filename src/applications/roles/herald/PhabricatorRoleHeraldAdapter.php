<?php

final class PhabricatorRoleHeraldAdapter extends HeraldAdapter {

  private $role;

  protected function newObject() {
    return new PhabricatorRole();
  }

  public function getAdapterApplicationClass() {
    return 'PhabricatorRoleApplication';
  }

  public function getAdapterContentDescription() {
    return pht('React to roles being created or updated.');
  }

  protected function initializeNewAdapter() {
    $this->role = $this->newObject();
  }

  public function supportsApplicationEmail() {
    return true;
  }

  public function supportsRuleType($rule_type) {
    switch ($rule_type) {
      case HeraldRuleTypeConfig::RULE_TYPE_GLOBAL:
      case HeraldRuleTypeConfig::RULE_TYPE_PERSONAL:
        return true;
      case HeraldRuleTypeConfig::RULE_TYPE_OBJECT:
      default:
        return false;
    }
  }

  public function setRole(PhabricatorRole $role) {
    $this->role = $role;
    return $this;
  }

  public function getRole() {
    return $this->role;
  }

  public function getObject() {
    return $this->role;
  }

  public function getAdapterContentName() {
    return pht('Roles');
  }

  public function getHeraldName() {
    return pht('Role %s', $this->getRole()->getName());
  }

}
