<?php

final class PholioMockMailReceiver extends PhabricatorObjectMailReceiver {

  public function isEnabled() {
    $app_class = 'PhabricatorPholioApplication';
    return PhabricatorApplication::isClassInstalled($app_class);
  }

  protected function getObjectPattern() {
    return 'M[1-9]\d*';
  }

  protected function loadObject($pattern, PhabricatorUser $viewer) {
    $id = (int)trim($pattern, 'M');

    return id(new PholioMockQuery())
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
