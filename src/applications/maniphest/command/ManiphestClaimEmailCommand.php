<?php

final class ManiphestClaimEmailCommand
  extends ManiphestEmailCommand {

  public function getCommand() {
    return 'claim';
  }

  public function getCommandSummary() {
    return pht(
      'Assign yourself as the owner of a task. To assign another user, '.
      'see `%s`.',
      '!assign');
  }

  public function buildTransactions(
    PhabricatorUser $viewer,
    PhabricatorApplicationTransactionInterface $object,
    PhabricatorMetaMTAReceivedMail $mail,
    $command,
    array $argv) {
    $xactions = array();

    $xactions[] = $object->getApplicationTransactionTemplate()
      ->setTransactionType(ManiphestTaskOwnerTransaction::TRANSACTIONTYPE)
      ->setNewValue($viewer->getPHID());

    return $xactions;
  }

}
