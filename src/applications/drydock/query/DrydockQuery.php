<?php

abstract class DrydockQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  public function getQueryApplicationClass() {
    return 'PhabricatorApplicationDrydock';
  }

}
