<?php

/**
 * @group paste
 */
final class PasteMockMailReceiver extends PhabricatorObjectMailReceiver {

  public function isEnabled() {
    $app_class = 'PhabricatorApplicationPaste';
    return PhabricatorApplication::isClassInstalled($app_class);
  }

  protected function getObjectPattern() {
    return 'P[1-9]\d*';
  }

  protected function loadObject($pattern, PhabricatorUser $viewer) {
    $id = (int)trim($pattern, 'P');

    return id(new PhabricatorPasteQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
  }

  protected function processReceivedObjectMail(
    PhabricatorMetaMTAReceivedMail $mail,
    PhabricatorLiskDAO $object,
    PhabricatorUser $sender) {

    $handler = id(new PasteReplyHandler())
      ->setMailReceiver($object);

    $handler->setActor($sender);
    $handler->setExcludeMailRecipientPHIDs(
      $mail->loadExcludeMailRecipientPHIDs());
    $handler->processEmail($mail);
  }

}
