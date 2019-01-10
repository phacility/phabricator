<?php

final class ManiphestCreateMailReceiver
  extends PhabricatorApplicationMailReceiver {

  protected function newApplication() {
    return new PhabricatorManiphestApplication();
  }

  protected function processReceivedMail(
    PhabricatorMetaMTAReceivedMail $mail,
    PhabricatorUser $sender) {

    $task = ManiphestTask::initializeNewTask($sender);
    $task->setOriginalEmailSource($mail->getHeader('From'));

    $handler = new ManiphestReplyHandler();
    $handler->setMailReceiver($task);

    $handler->setActor($sender);
    $handler->setExcludeMailRecipientPHIDs(
      $mail->loadAllRecipientPHIDs());
    if ($this->getApplicationEmail()) {
      $handler->setApplicationEmail($this->getApplicationEmail());
    }
    $handler->processEmail($mail);

    $mail->setRelatedPHID($task->getPHID());
  }

}
