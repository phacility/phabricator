<?php

final class ManiphestTaskMailReceiver extends PhabricatorObjectMailReceiver {

  public function isEnabled() {
    $app_class = 'PhabricatorManiphestApplication';
    return PhabricatorApplication::isClassInstalled($app_class);
  }

  protected function getObjectPattern() {
    return 'T[1-9]\d*';
  }

  protected function loadObject($pattern, PhabricatorUser $viewer) {
    $id = (int)trim($pattern, 'T');

    $results = id(new ManiphestTaskQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->execute();

    return head($results);
  }

  protected function processReceivedObjectMail(
    PhabricatorMetaMTAReceivedMail $mail,
    PhabricatorLiskDAO $object,
    PhabricatorUser $sender) {

    $handler = PhabricatorEnv::newObjectFromConfig(
      'metamta.maniphest.reply-handler');
    $handler->setMailReceiver($object);

    $handler->setActor($sender);
    $handler->setExcludeMailRecipientPHIDs(
      $mail->loadExcludeMailRecipientPHIDs());
    $handler->processEmail($mail);
  }

}
