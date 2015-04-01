<?php

final class PasteReplyHandler
  extends PhabricatorApplicationTransactionReplyHandler {

  public function validateMailReceiver($mail_receiver) {
    if (!($mail_receiver instanceof PhabricatorPaste)) {
      throw new Exception('Mail receiver is not a PhabricatorPaste.');
    }
  }

  public function getObjectPrefix() {
    return 'P';
  }

  protected function processMailCommands(array $commands) {
   $actor = $this->getActor();

    $xactions = array();
    foreach ($commands as $command) {
      switch (head($command)) {
        case 'unsubscribe':
          $xaction = id(new PhabricatorPasteTransaction())
            ->setTransactionType(PhabricatorTransactions::TYPE_SUBSCRIBERS)
            ->setNewValue(array('-' => array($actor->getPHID())));
          $xactions[] = $xaction;
          break;
      }
    }

    return $xactions;
  }

}
