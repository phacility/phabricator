<?php

final class PhabricatorMacroMailReceiver extends PhabricatorObjectMailReceiver {

  public function isEnabled() {
    $app_class = 'PhabricatorApplicationManiphest';
    return PhabricatorApplication::isClassInstalled($app_class);
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

  protected function processReceivedObjectMail(
    PhabricatorMetaMTAReceivedMail $mail,
    PhabricatorLiskDAO $object,
    PhabricatorUser $sender) {

    // TODO: For now, we just drop this mail on the floor.

  }


}
