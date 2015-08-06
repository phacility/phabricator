<?php

final class PhabricatorProjectRemoveHeraldAction
  extends PhabricatorProjectHeraldAction {

  const ACTIONCONST = 'projects.remove';

  public function getHeraldActionName() {
    return pht('Remove projects');
  }

  public function applyEffect($object, HeraldEffect $effect) {
    return $this->applyProjects($effect->getTarget(), $is_add = false);
  }

  public function getHeraldActionStandardType() {
    return self::STANDARD_PHID_LIST;
  }

  protected function getDatasource() {
    return new PhabricatorProjectDatasource();
  }

  public function renderActionDescription($value) {
    return pht('Remove projects: %s.', $this->renderHandleList($value));
  }

}
