<?php

abstract class PhabricatorPackagesQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  public function getQueryApplicationClass() {
    return 'PhabricatorPackagesApplication';
  }

}
