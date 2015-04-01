<?php

final class FileReplyHandler
  extends PhabricatorApplicationTransactionReplyHandler {

  public function validateMailReceiver($mail_receiver) {
    if (!($mail_receiver instanceof PhabricatorFile)) {
      throw new Exception('Mail receiver is not a PhabricatorFile.');
    }
  }

  public function getObjectPrefix() {
    return 'F';
  }

  protected function processMailCommands(array $commands) {
   $actor = $this->getActor();

    $xactions = array();
    foreach ($commands as $command) {
      switch (head($command)) {
        case 'unsubscribe':
          $xaction = id(new PhabricatorFileTransaction())
            ->setTransactionType(PhabricatorTransactions::TYPE_SUBSCRIBERS)
            ->setNewValue(array('-' => array($actor->getPHID())));
          $xactions[] = $xaction;
          break;
      }
    }

    return $xactions;
  }

}
