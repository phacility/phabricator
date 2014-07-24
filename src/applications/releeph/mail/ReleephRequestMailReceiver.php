<?php

final class ReleephRequestMailReceiver extends PhabricatorObjectMailReceiver {

  public function isEnabled() {
    $app_class = 'PhabricatorReleephApplication';
    return PhabricatorApplication::isClassInstalled($app_class);
  }

  protected function getObjectPattern() {
    return 'RQ[1-9]\d*';
  }

  protected function loadObject($pattern, PhabricatorUser $viewer) {
    $id = (int)substr($pattern, 2);

    return id(new ReleephRequestQuery())
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
