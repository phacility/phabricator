<?php

final class PhabricatorProjectHeraldAdapter extends HeraldAdapter {

  private $project;

  protected function newObject() {
    return new PhabricatorProject();
  }

  public function getAdapterApplicationClass() {
    return 'PhabricatorProjectApplication';
  }

  public function getAdapterContentDescription() {
    return pht('React to projects being created or updated.');
  }

  protected function initializeNewAdapter() {
    $this->project = $this->newObject();
  }

  public function supportsApplicationEmail() {
    return true;
  }

  public function getRepetitionOptions() {
    return array(
      HeraldRepetitionPolicyConfig::EVERY,
      HeraldRepetitionPolicyConfig::FIRST,
    );
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

  public function setProject(PhabricatorProject $project) {
    $this->project = $project;
    return $this;
  }

  public function getProject() {
    return $this->project;
  }

  public function getObject() {
    return $this->project;
  }

  public function getAdapterContentName() {
    return pht('Projects');
  }

  public function getHeraldName() {
    return pht('Project %s', $this->getProject()->getName());
  }

}
