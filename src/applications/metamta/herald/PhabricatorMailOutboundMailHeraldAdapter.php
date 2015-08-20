<?php

final class PhabricatorMailOutboundMailHeraldAdapter
  extends HeraldAdapter {

  private $mail;

  public function getAdapterApplicationClass() {
    return 'PhabricatorMetaMTAApplication';
  }

  public function getAdapterContentDescription() {
    return pht('Route outbound email.');
  }

  protected function initializeNewAdapter() {
    $this->mail = $this->newObject();
  }

  protected function newObject() {
    return new PhabricatorMetaMTAMail();
  }

  public function getObject() {
    return $this->mail;
  }

  public function setObject(PhabricatorMetaMTAMail $mail) {
    $this->mail = $mail;
    return $this;
  }

  public function getAdapterContentName() {
    return pht('Outbound Mail');
  }

  public function isSingleEventAdapter() {
    return true;
  }

  public function getRepetitionOptions() {
    return array(
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

  public function getHeraldName() {
    return pht('Mail %d', $this->getObject()->getID());
  }

}
