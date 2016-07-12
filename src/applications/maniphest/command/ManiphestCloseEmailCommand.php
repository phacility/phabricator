<?php

final class ManiphestCloseEmailCommand
  extends ManiphestEmailCommand {

  public function getCommand() {
    return 'close';
  }

  public function getCommandSummary() {
    return pht(
      'Close a task. This changes the task status to the default closed '.
      'status. For a more powerful (but less concise) way to change task '.
      'statuses, see `%s`.',
      '!status');
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
