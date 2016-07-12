<?php

final class DifferentialReviewersAddBlockingReviewersHeraldAction
  extends DifferentialReviewersHeraldAction {

  const ACTIONCONST = 'differential.reviewers.blocking';

  public function getHeraldActionName() {
    return pht('Add blocking reviewers');
  }

  public function supportsRuleType($rule_type) {
    return ($rule_type != HeraldRuleTypeConfig::RULE_TYPE_PERSONAL);
  }

  public function applyEffect($object, HeraldEffect $effect) {
    return $this->applyReviewers($effect->getTarget(), $is_blocking = true);
  }

  public function getHeraldActionStandardType() {
    return self::STANDARD_PHID_LIST;
  }

  protected function getDatasource() {
    return new DiffusionAuditorDatasource();
  }

  public function renderActionDescription($value) {
    return pht('Add blocking reviewers: %s.', $this->renderHandleList($value));
  }

}
