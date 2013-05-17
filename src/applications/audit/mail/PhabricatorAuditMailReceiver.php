<?php

final class PhabricatorAuditMailReceiver extends PhabricatorObjectMailReceiver {

  public function isEnabled() {
    $app_class = 'PhabricatorApplicationAudit';
    return PhabricatorApplication::isClassInstalled($app_class);
  }

  protected function getObjectPattern() {
    return 'C[1-9]\d*';
  }

  protected function loadObject($pattern, PhabricatorUser $viewer) {
    $id = (int)trim($pattern, 'C');

    return id(new DiffusionCommitQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
  }

}
