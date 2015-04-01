<?php

final class ReleephRequestMailReceiver extends PhabricatorObjectMailReceiver {

  public function isEnabled() {
    $app_class = 'PhabricatorReleephApplication';
    return PhabricatorApplication::isClassInstalled($app_class);
  }

  protected function getObjectPattern() {
    return 'Y[1-9]\d*';
  }

  protected function loadObject($pattern, PhabricatorUser $viewer) {
    $id = (int)substr($pattern, 1);

    return id(new ReleephRequestQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
  }

  protected function getTransactionReplyHandler() {
    return new ReleephRequestReplyHandler();
  }

}
