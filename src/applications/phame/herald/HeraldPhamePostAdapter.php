<?php

final class HeraldPhamePostAdapter extends HeraldAdapter {

  private $post;

  protected function newObject() {
    return new PhamePost();
  }

  public function getAdapterApplicationClass() {
    return 'PhabricatorPhameApplication';
  }

  public function getAdapterContentDescription() {
    return pht('React to Phame Posts being created or updated.');
  }

  protected function initializeNewAdapter() {
    $this->post = $this->newObject();
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

  public function setPost(PhamePost $post) {
    $this->post = $post;
    return $this;
  }

  public function getObject() {
    return $this->post;
  }

  public function getAdapterContentName() {
    return pht('Phame Posts');
  }

  public function getHeraldName() {
    return 'POST'.$this->getObject()->getID();
  }

}
