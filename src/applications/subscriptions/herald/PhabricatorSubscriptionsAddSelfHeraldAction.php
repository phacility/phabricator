<?php

final class PhabricatorSubscriptionsAddSelfHeraldAction
  extends PhabricatorSubscriptionsHeraldAction {

  const ACTIONCONST = 'subscribers.self.add';

  public function getHeraldActionName() {
    return pht('Add me as a subscriber');
  }

  public function supportsRuleType($rule_type) {
    return ($rule_type == HeraldRuleTypeConfig::RULE_TYPE_PERSONAL);
  }

  public function applyEffect($object, HeraldEffect $effect) {
    $phid = $effect->getRule()->getAuthorPHID();
    return $this->applySubscribe(array($phid), $is_add = true);
  }

  public function getHeraldActionStandardType() {
    return self::STANDARD_NONE;
  }

  public function renderActionDescription($value) {
    return pht('Add rule author as subscriber.');
  }

}
