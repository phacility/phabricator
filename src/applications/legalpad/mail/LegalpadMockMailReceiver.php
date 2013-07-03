<?php

final class LegalpadMockMailReceiver extends PhabricatorObjectMailReceiver {

  public function isEnabled() {
    $app_class = 'PhabricatorApplicationLegalpad';
    return PhabricatorApplication::isClassInstalled($app_class);
  }

  protected function getObjectPattern() {
    return 'L[1-9]\d*';
  }

  protected function loadObject($pattern, PhabricatorUser $viewer) {
    $id = (int)trim($pattern, 'L');

    return id(new LegalpadDocumentQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->needDocumentBodies(true)
      ->executeOne();
  }

  protected function processReceivedObjectMail(
    PhabricatorMetaMTAReceivedMail $mail,
    PhabricatorLiskDAO $object,
    PhabricatorUser $sender) {

    $handler = id(new LegalpadReplyHandler())
      ->setMailReceiver($object)
      ->setActor($sender)
      ->setExcludeMailRecipientPHIDs(
        $mail->loadExcludeMailRecipientPHIDs());

    return $handler->processEmail($mail);
  }

}
