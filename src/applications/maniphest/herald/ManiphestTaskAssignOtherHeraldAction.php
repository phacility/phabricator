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
    return id(new ManiphestAssigneeDatasource())
      ->setLimit(1);
  }

  public function renderActionDescription($value) {
    if (head($value) === PhabricatorPeopleNoOwnerDatasource::FUNCTION_TOKEN) {
      return pht('Unassign task.');
    } else {
      return pht('Assign task to: %s.', $this->renderHandleList($value));
    }
  }

}
