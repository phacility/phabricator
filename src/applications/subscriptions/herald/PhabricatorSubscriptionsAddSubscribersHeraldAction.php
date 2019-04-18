<?php

final class PhabricatorSubscriptionsAddSubscribersHeraldAction
  extends PhabricatorSubscriptionsHeraldAction {

  const ACTIONCONST = 'subscribers.add';

  public function getHeraldActionName() {
    return pht('Add subscribers');
  }

  public function supportsRuleType($rule_type) {
    return ($rule_type != HeraldRuleTypeConfig::RULE_TYPE_PERSONAL);
  }

  public function applyEffect($object, HeraldEffect $effect) {
    return $this->applySubscribe($effect->getTarget(), $is_add = true);
  }

  public function getHeraldActionStandardType() {
    return self::STANDARD_PHID_LIST;
  }

  protected function getDatasource() {
    return new PhabricatorMetaMTAMailableDatasource();
  }

  public function renderActionDescription($value) {
    return pht('Add subscribers: %s.', $this->renderHandleList($value));
  }

  public function getPHIDsAffectedByAction(HeraldActionRecord $record) {
    return $record->getTarget();
  }

}
