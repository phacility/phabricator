<?php

final class PhabricatorRoleRemoveHeraldAction
  extends PhabricatorRoleHeraldAction {

  const ACTIONCONST = 'roles.remove';

  public function getHeraldActionName() {
    return pht('Remove roles');
  }

  public function applyEffect($object, HeraldEffect $effect) {
    return $this->applyRoles($effect->getTarget(), $is_add = false);
  }

  public function getHeraldActionStandardType() {
    return self::STANDARD_PHID_LIST;
  }

  protected function getDatasource() {
    return new PhabricatorRoleDatasource();
  }

  public function renderActionDescription($value) {
    return pht('Remove roles: %s.', $this->renderHandleList($value));
  }

}
