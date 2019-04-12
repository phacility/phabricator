<?php

final class DifferentialReviewersAddReviewersHeraldAction
  extends DifferentialReviewersHeraldAction {

  const ACTIONCONST = 'differential.reviewers.add';

  public function getHeraldActionName() {
    return pht('Add reviewers');
  }

  public function supportsRuleType($rule_type) {
    return ($rule_type != HeraldRuleTypeConfig::RULE_TYPE_PERSONAL);
  }

  public function applyEffect($object, HeraldEffect $effect) {
    return $this->applyReviewers($effect->getTarget(), $is_blocking = false);
  }

  public function getHeraldActionStandardType() {
    return self::STANDARD_PHID_LIST;
  }

  protected function getDatasource() {
    return new DiffusionAuditorDatasource();
  }

  public function renderActionDescription($value) {
    return pht('Add reviewers: %s.', $this->renderHandleList($value));
  }

  public function getPHIDsAffectedByAction(HeraldActionRecord $record) {
    return $record->getTarget();
  }

}
