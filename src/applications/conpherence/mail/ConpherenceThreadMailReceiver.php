<?php

final class ConpherenceThreadMailReceiver
  extends PhabricatorObjectMailReceiver {

  public function isEnabled() {
    $app_class = 'PhabricatorConpherenceApplication';
    return PhabricatorApplication::isClassInstalled($app_class);
  }

  protected function getObjectPattern() {
    return 'E[1-9]\d*';
  }

  protected function loadObject($pattern, PhabricatorUser $viewer) {
    $id = (int)trim($pattern, 'E');

    return id(new ConpherenceThreadQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
  }

  protected function processReceivedObjectMail(
    PhabricatorMetaMTAReceivedMail $mail,
    PhabricatorLiskDAO $object,
    PhabricatorUser $sender) {

    $handler = id(new ConpherenceReplyHandler())
      ->setMailReceiver($object);

    $handler->setActor($sender);
    $handler->setExcludeMailRecipientPHIDs(
      $mail->loadExcludeMailRecipientPHIDs());
    $handler->processEmail($mail);
  }

}
