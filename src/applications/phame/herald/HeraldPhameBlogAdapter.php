<?php

final class HeraldPhameBlogAdapter extends HeraldAdapter {

  private $blog;

  protected function newObject() {
    return new PhameBlog();
  }

  public function getAdapterApplicationClass() {
    return 'PhabricatorPhameApplication';
  }

  public function getAdapterContentDescription() {
    return pht('React to Phame Blogs being created or updated.');
  }

  protected function initializeNewAdapter() {
    $this->blog = $this->newObject();
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

  public function setBlog(PhameBlog $blog) {
    $this->blog = $blog;
    return $this;
  }

  public function getObject() {
    return $this->blog;
  }

  public function getAdapterContentName() {
    return pht('Phame Blogs');
  }

  public function getHeraldName() {
    return 'BLOG'.$this->getObject()->getID();
  }

}
