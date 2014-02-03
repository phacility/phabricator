<?php

final class PhabricatorSearchDocumentQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  protected function loadPage() {
    return array();
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorApplicationSearch';
  }

}
