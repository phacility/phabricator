<?php

final class DifferentialReviewersAddSelfHeraldAction
  extends DifferentialReviewersHeraldAction {

  const ACTIONCONST = 'differential.reviewers.self.add';

  public function getHeraldActionName() {
    return pht('Add me as a reviewer');
  }

  public function supportsRuleType($rule_type) {
    return ($rule_type == HeraldRuleTypeConfig::RULE_TYPE_PERSONAL);
  }

  public function applyEffect($object, HeraldEffect $effect) {
    $phid = $effect->getRule()->getAuthorPHID();
    return $this->applyReviewers(array($phid), $is_blocking = false);
  }

  public function getHeraldActionStandardType() {
    return self::STANDARD_NONE;
  }

  public function renderActionDescription($value) {
    return pht('Add rule author as reviewer.');
  }

}
