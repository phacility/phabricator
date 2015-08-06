<?php

final class PhabricatorSubscriptionsRemoveSelfHeraldAction
  extends PhabricatorSubscriptionsHeraldAction {

  const ACTIONCONST = 'subscribers.self.remove';

  public function getHeraldActionName() {
    return pht('Remove me as a subscriber');
  }

  public function supportsRuleType($rule_type) {
    return ($rule_type == HeraldRuleTypeConfig::RULE_TYPE_PERSONAL);
  }

  public function applyEffect($object, HeraldEffect $effect) {
    $phid = $effect->getRule()->getAuthorPHID();
    return $this->applySubscribe(array($phid), $is_add = false);
  }

  public function getHeraldActionStandardType() {
    return self::STANDARD_NONE;
  }

  public function renderActionDescription($value) {
    return pht('Remove rule author as subscriber.');
  }

}
