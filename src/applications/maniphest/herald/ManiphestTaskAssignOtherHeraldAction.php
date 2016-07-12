<?php

final class ManiphestTaskAssignOtherHeraldAction
  extends ManiphestTaskAssignHeraldAction {

  const ACTIONCONST = 'maniphest.assign.other';

  public function getHeraldActionName() {
    return pht('Assign task to');
  }

  public function supportsRuleType($rule_type) {
    return ($rule_type != HeraldRuleTypeConfig::RULE_TYPE_PERSONAL);
  }

  public function applyEffect($object, HeraldEffect $effect) {
    return $this->applyAssign($effect->getTarget());
  }

  public function getHeraldActionStandardType() {
    return self::STANDARD_PHID_LIST;
  }

  protected function getDatasource() {
    // TODO: Eventually, it would be nice to get "limit = 1" exported from here
    // up to the UI.
    return new ManiphestAssigneeDatasource();
  }

  public function renderActionDescription($value) {
    if (head($value) === PhabricatorPeopleNoOwnerDatasource::FUNCTION_TOKEN) {
      return pht('Unassign task.');
    } else {
      return pht('Assign task to: %s.', $this->renderHandleList($value));
    }
  }

}
