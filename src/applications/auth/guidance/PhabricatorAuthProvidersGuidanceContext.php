<?php

final class PhabricatorAuthProvidersGuidanceContext
  extends PhabricatorGuidanceContext {

  private $canManage = false;

  public function setCanManage($can_manage) {
    $this->canManage = $can_manage;
    return $this;
  }

  public function getCanManage() {
    return $this->canManage;
  }

}
