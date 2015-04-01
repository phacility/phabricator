<?php

final class ManiphestAssignEmailCommand
  extends ManiphestEmailCommand {

  public function getCommand() {
    return 'assign';
  }

  public function getCommandSyntax() {
    return '**!assign** //username//';
  }

  public function getCommandSummary() {
    return pht('Assign a task to a specific user.');
  }

  public function getCommandDescription() {
    return pht(
      'To assign a task to another user, provide their username. For example, '.
      'to assign a task to `alincoln`, write `!assign alincoln`.'.
      "\n\n".
      'If you omit the username or the username is not valid, this behaves '.
      'like `!claim` and assigns the task to you instead.');
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
