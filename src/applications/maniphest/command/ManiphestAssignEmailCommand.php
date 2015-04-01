<?php

final class ManiphestAssignEmailCommand
  extends ManiphestEmailCommand {

  public function getCommand() {
    return 'assign';
  }

  public function buildTransactions(
    PhabricatorUser $viewer,
    PhabricatorApplicationTransactionInterface $object,
    PhabricatorMetaMTAReceivedMail $mail,
    $command,
    array $argv) {
    $xactions = array();


    $assign_to = head($argv);
    if ($assign_to) {
      $assign_user = id(new PhabricatorPeopleQuery())
        ->setViewer($viewer)
        ->withUsernames(array($assign_to))
        ->executeOne();
      if ($assign_user) {
        $assign_phid = $assign_user->getPHID();
      }
    }

    // Treat bad "!assign" like "!claim".
    if (!$assign_phid) {
      $assign_phid = $viewer->getPHID();
    }

    $xactions[] = $object->getApplicationTransactionTemplate()
      ->setTransactionType(ManiphestTransaction::TYPE_OWNER)
      ->setNewValue($assign_phid);

    return $xactions;
  }

}
