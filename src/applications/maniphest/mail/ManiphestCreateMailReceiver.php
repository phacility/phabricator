<?php

final class ManiphestCreateMailReceiver extends PhabricatorMailReceiver {

  public function isEnabled() {
    $app_class = 'PhabricatorManiphestApplication';
    return PhabricatorApplication::isClassInstalled($app_class);
  }

  public function canAcceptMail(PhabricatorMetaMTAReceivedMail $mail) {
    $maniphest_app = new PhabricatorManiphestApplication();
    return $this->canAcceptApplicationMail($maniphest_app, $mail);
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
      $mail->loadExcludeMailRecipientPHIDs());
    if ($this->getApplicationEmail()) {
      $handler->setApplicationEmail($this->getApplicationEmail());
    }
    $handler->processEmail($mail);

    $mail->setRelatedPHID($task->getPHID());
  }

}
