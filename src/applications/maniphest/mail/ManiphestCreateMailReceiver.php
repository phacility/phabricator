<?php

final class ManiphestCreateMailReceiver extends PhabricatorMailReceiver {

  public function isEnabled() {
    $app_class = 'PhabricatorManiphestApplication';
    return PhabricatorApplication::isClassInstalled($app_class);
  }

  public function canAcceptMail(PhabricatorMetaMTAReceivedMail $mail) {
    $maniphest_app = new PhabricatorManiphestApplication();
    $application_emails = id(new PhabricatorMetaMTAApplicationEmailQuery())
      ->setViewer($this->getViewer())
      ->withApplicationPHIDs(array($maniphest_app->getPHID()))
      ->execute();

    foreach ($mail->getToAddresses() as $to_address) {
      foreach ($application_emails as $application_email) {
        $create_address = $application_email->getAddress();
        if ($this->matchAddresses($create_address, $to_address)) {
          $this->setApplicationEmail($application_email);
          return true;
        }
      }
    }

    return false;
  }

  protected function processReceivedMail(
    PhabricatorMetaMTAReceivedMail $mail,
    PhabricatorUser $sender) {

    $task = ManiphestTask::initializeNewTask($sender);
    $task->setOriginalEmailSource($mail->getHeader('From'));

    $handler = PhabricatorEnv::newObjectFromConfig(
      'metamta.maniphest.reply-handler');
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
