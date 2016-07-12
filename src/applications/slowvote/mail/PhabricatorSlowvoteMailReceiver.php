<?php

final class PhabricatorSlowvoteMailReceiver
  extends PhabricatorObjectMailReceiver {

  public function isEnabled() {
    return PhabricatorApplication::isClassInstalled(
      'PhabricatorSlowvoteApplication');
  }

  protected function getObjectPattern() {
    return 'V[1-9]\d*';
  }

  protected function loadObject($pattern, PhabricatorUser $viewer) {
    $id = (int)substr($pattern, 4);

    return id(new PhabricatorSlowvoteQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
  }

  protected function getTransactionReplyHandler() {
    return new PhabricatorSlowvoteReplyHandler();
  }

}
