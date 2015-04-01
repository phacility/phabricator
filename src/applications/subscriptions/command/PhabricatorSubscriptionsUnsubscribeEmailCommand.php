<?php

final class PhabricatorSubscriptionsUnsubscribeEmailCommand
  extends MetaMTAEmailTransactionCommand {

  public function getCommand() {
    return 'unsubscribe';
  }

  public function getCommandSummary() {
    return pht('Remove yourself as a subscriber.');
  }

  public function isCommandSupportedForObject(
    PhabricatorApplicationTransactionInterface $object) {
    return ($object instanceof PhabricatorSubscribableInterface);
  }

  public function buildTransactions(
    PhabricatorUser $viewer,
    PhabricatorApplicationTransactionInterface $object,
    PhabricatorMetaMTAReceivedMail $mail,
    $command,
    array $argv) {
    $xactions = array();

    $xactions[] = $object->getApplicationTransactionTemplate()
      ->setTransactionType(PhabricatorTransactions::TYPE_SUBSCRIBERS)
      ->setNewValue(
        array(
          '-' => array($viewer->getPHID()),
        ));

    return $xactions;
  }

}
