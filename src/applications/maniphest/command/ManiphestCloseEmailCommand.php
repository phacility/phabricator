<?php

final class ManiphestCloseEmailCommand
  extends ManiphestEmailCommand {

  public function getCommand() {
    return 'close';
  }

  public function getCommandSummary() {
    return pht('Close a task.');
  }

  public function buildTransactions(
    PhabricatorUser $viewer,
    PhabricatorApplicationTransactionInterface $object,
    PhabricatorMetaMTAReceivedMail $mail,
    $command,
    array $argv) {
    $xactions = array();

    $xactions[] = $object->getApplicationTransactionTemplate()
      ->setTransactionType(ManiphestTransaction::TYPE_STATUS)
      ->setNewValue(ManiphestTaskStatus::getDefaultClosedStatus());

    return $xactions;
  }

}
