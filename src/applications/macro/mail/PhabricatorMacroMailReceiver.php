<?php

final class PhabricatorMacroMailReceiver extends PhabricatorObjectMailReceiver {

  public function isEnabled() {
    return PhabricatorApplication::isClassInstalled(
      'PhabricatorManiphestApplication');
  }

  protected function getObjectPattern() {
    return 'MCRO[1-9]\d*';
  }

  protected function loadObject($pattern, PhabricatorUser $viewer) {
    $id = (int)substr($pattern, 4);

    return id(new PhabricatorMacroQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
  }

  protected function getTransactionReplyHandler() {
    return new PhabricatorMacroReplyHandler();
  }

}
