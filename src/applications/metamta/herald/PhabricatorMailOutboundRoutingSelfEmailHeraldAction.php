<?php

final class PhabricatorMailOutboundRoutingSelfEmailHeraldAction
  extends PhabricatorMailOutboundRoutingHeraldAction {

  const ACTIONCONST = 'routing.self.email';

  public function getHeraldActionName() {
    return pht('Deliver as email');
  }

  public function supportsRuleType($rule_type) {
    return ($rule_type == HeraldRuleTypeConfig::RULE_TYPE_PERSONAL);
  }

  public function applyEffect($object, HeraldEffect $effect) {
    $rule = $effect->getRule();
    $author_phid = $rule->getAuthorPHID();

    $this->applyRouting(
      $rule,
      PhabricatorMailRoutingRule::ROUTE_AS_MAIL,
      array($author_phid));
  }

  public function getHeraldActionStandardType() {
    return self::STANDARD_NONE;
  }

  public function renderActionDescription($value) {
    return pht('Deliver as email.');
  }

}
