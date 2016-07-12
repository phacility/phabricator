<?php

final class ManiphestTaskAssignSelfHeraldAction
  extends ManiphestTaskAssignHeraldAction {

  const ACTIONCONST = 'maniphest.assign.self';

  public function getHeraldActionName() {
    return pht('Assign task to me');
  }

  public function supportsRuleType($rule_type) {
    return ($rule_type == HeraldRuleTypeConfig::RULE_TYPE_PERSONAL);
  }

  public function applyEffect($object, HeraldEffect $effect) {
    $phid = $effect->getRule()->getAuthorPHID();
    return $this->applyAssign(array($phid));
  }

  public function getHeraldActionStandardType() {
    return self::STANDARD_NONE;
  }

  public function renderActionDescription($value) {
    return pht('Assign task to rule author.');
  }

}
