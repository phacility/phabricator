<?php

final class PhabricatorMetaMTAEmailOthersHeraldAction
  extends PhabricatorMetaMTAEmailHeraldAction {

  const ACTIONCONST = 'email.other';

  public function getHeraldActionName() {
    return pht('Send an email to');
  }

  public function supportsRuleType($rule_type) {
    return ($rule_type != HeraldRuleTypeConfig::RULE_TYPE_PERSONAL);
  }

  public function applyEffect($object, HeraldEffect $effect) {
    return $this->applyEmail($effect->getTarget(), $force = false);
  }

  public function getHeraldActionStandardType() {
    return self::STANDARD_PHID_LIST;
  }

  protected function getDatasource() {
    return new PhabricatorMetaMTAMailableDatasource();
  }

  public function renderActionDescription($value) {
    return pht('Send an email to: %s.', $this->renderHandleList($value));
  }

}
