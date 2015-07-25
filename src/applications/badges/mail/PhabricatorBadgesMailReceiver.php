<?php

final class PhabricatorBadgesMailReceiver
  extends PhabricatorObjectMailReceiver {

  public function isEnabled() {
    return PhabricatorApplication::isClassInstalled(
      'PhabricatorBadgesApplication');
  }

  protected function getObjectPattern() {
    return 'BDGE[1-9]\d*';
  }

  protected function loadObject($pattern, PhabricatorUser $viewer) {
    $id = (int)substr($pattern, 4);

    return id(new PhabricatorBadgesQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
  }

  protected function getTransactionReplyHandler() {
    return new PhabricatorBadgesReplyHandler();
  }

}
