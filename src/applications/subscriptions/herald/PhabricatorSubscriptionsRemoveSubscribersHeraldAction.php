<?php

final class PhabricatorSubscriptionsRemoveSubscribersHeraldAction
  extends PhabricatorSubscriptionsHeraldAction {

  const ACTIONCONST = 'subscribers.remove';

  public function getHeraldActionName() {
    return pht('Remove subscribers');
  }

  public function supportsRuleType($rule_type) {
    return ($rule_type != HeraldRuleTypeConfig::RULE_TYPE_PERSONAL);
  }

  public function applyEffect($object, HeraldEffect $effect) {
    return $this->applySubscribe($effect->getTarget(), $is_add = false);
  }

  public function getHeraldActionStandardType() {
    return self::STANDARD_PHID_LIST;
  }

  protected function getDatasource() {
    return new PhabricatorMetaMTAMailableDatasource();
  }

  public function renderActionDescription($value) {
    return pht('Remove subscribers: %s.', $this->renderHandleList($value));
  }

}
