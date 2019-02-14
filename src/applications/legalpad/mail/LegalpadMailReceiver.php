<?php

final class LegalpadMailReceiver extends PhabricatorObjectMailReceiver {

  public function isEnabled() {
    return PhabricatorApplication::isClassInstalled(
      'PhabricatorLegalpadApplication');
  }

  protected function getObjectPattern() {
    return 'L[1-9]\d*';
  }

  protected function loadObject($pattern, PhabricatorUser $viewer) {
    $id = (int)substr($pattern, 1);

    return id(new LegalpadDocumentQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->needDocumentBodies(true)
      ->executeOne();
  }

  protected function getTransactionReplyHandler() {
    return new LegalpadReplyHandler();
  }

}
