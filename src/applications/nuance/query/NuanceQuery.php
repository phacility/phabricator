<?php

abstract class NuanceQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  public function getQueryApplicationClass() {
    return 'PhabricatorApplicationNuance';
  }

}

