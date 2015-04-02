<?php

final class ManiphestReplyHandler
  extends PhabricatorApplicationTransactionReplyHandler {

  public function validateMailReceiver($mail_receiver) {
    if (!($mail_receiver instanceof ManiphestTask)) {
      throw new Exception('Mail receiver is not a ManiphestTask!');
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

    $xactions = array();

    if ($is_new) {
      $xactions[] = $object->getApplicationTransactionTemplate()
        ->setTransactionType(ManiphestTransaction::TYPE_TITLE)
        ->setNewValue(nonempty($mail->getSubject(), pht('Untitled Task')));

      $xactions[] = $object->getApplicationTransactionTemplate()
        ->setTransactionType(ManiphestTransaction::TYPE_DESCRIPTION)
        ->setNewValue($body);
    }

    return $xactions;
  }


}
