<?php

final class PonderQuestionMailReceiver extends PhabricatorObjectMailReceiver {

  public function isEnabled() {
    $app_class = 'PhabricatorApplicationPonder';
    return PhabricatorApplication::isClassInstalled($app_class);
  }

  protected function getObjectPattern() {
    return 'Q[1-9]\d*';
  }

  protected function loadObject($pattern, PhabricatorUser $viewer) {
    $id = (int)trim($pattern, 'Q');

    return id(new PonderQuestionQuery())
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
