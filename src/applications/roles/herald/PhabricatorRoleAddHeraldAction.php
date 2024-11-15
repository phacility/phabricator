<?php

final class PhabricatorRoleAddHeraldAction
  extends PhabricatorRoleHeraldAction {

  const ACTIONCONST = 'roles.add';

  public function getHeraldActionName() {
    return pht('Add roles');
  }

  public function applyEffect($object, HeraldEffect $effect) {
    return $this->applyRoles($effect->getTarget(), $is_add = true);
  }

  public function getHeraldActionStandardType() {
    return self::STANDARD_PHID_LIST;
  }

  protected function getDatasource() {
    return new PhabricatorRoleDatasource();
  }

  public function renderActionDescription($value) {
    return pht('Add roles: %s.', $this->renderHandleList($value));
  }

}
