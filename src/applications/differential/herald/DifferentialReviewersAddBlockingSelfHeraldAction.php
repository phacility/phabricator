<?php

final class DifferentialReviewersAddBlockingSelfHeraldAction
  extends DifferentialReviewersHeraldAction {

  const ACTIONCONST = 'differential.reviewers.self.blocking';

  public function getHeraldActionName() {
    return pht('Add me as a blocking reviewer');
  }

  public function supportsRuleType($rule_type) {
    return ($rule_type == HeraldRuleTypeConfig::RULE_TYPE_PERSONAL);
  }

  public function applyEffect($object, HeraldEffect $effect) {
    $phid = $effect->getRule()->getAuthorPHID();
    return $this->applyReviewers(array($phid), $is_blocking = true);
  }

  public function getHeraldActionStandardType() {
    return self::STANDARD_NONE;
  }

  public function renderActionDescription($value) {
    return pht('Add rule author as blocking reviewer.');
  }

}
