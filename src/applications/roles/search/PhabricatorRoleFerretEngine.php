<?php

final class PhabricatorRoleFerretEngine
  extends PhabricatorFerretEngine {

  public function getApplicationName() {
    return 'role';
  }

  public function getScopeName() {
    return 'role';
  }

  public function newSearchEngine() {
    return new PhabricatorRoleSearchEngine();
  }

}
