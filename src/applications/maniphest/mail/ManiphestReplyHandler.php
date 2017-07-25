<?php

final class ManiphestReplyHandler
  extends PhabricatorApplicationTransactionReplyHandler {

  public function validateMailReceiver($mail_receiver) {
    if (!($mail_receiver instanceof ManiphestTask)) {
      throw new Exception(pht('Mail receiver is not a %s!', 'ManiphestTask'));
    }
  }

  public function getObjectPrefix() {
    return 'T';
  }

  protected function didReceiveMail(
    PhabricatorMetaMTAReceivedMail $mail,
    $body) {

    $object = $this->getMailReceiver();
    $is_new = !$object->getID();
    $actor = $this->getActor();

    $xactions = array();

    if ($is_new) {
      $xactions[] = $this->newTransaction()
        ->setTransactionType(PhabricatorTransactions::TYPE_CREATE)
        ->setNewValue(true);

      $xactions[] = $this->newTransaction()
        ->setTransactionType(ManiphestTaskTitleTransaction::TRANSACTIONTYPE)
        ->setNewValue(nonempty($mail->getSubject(), pht('Untitled Task')));

      $xactions[] = $this->newTransaction()
        ->setTransactionType(
          ManiphestTaskDescriptionTransaction::TRANSACTIONTYPE)
        ->setNewValue($body);

      $actor_phid = $actor->getPHID();
      if ($actor_phid) {
        $xactions[] = $this->newTransaction()
          ->setTransactionType(PhabricatorTransactions::TYPE_SUBSCRIBERS)
          ->setNewValue(
            array(
              '+' => array($actor_phid),
            ));
      }
    }

    return $xactions;
  }


}
