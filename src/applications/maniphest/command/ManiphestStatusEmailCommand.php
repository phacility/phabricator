<?php

final class ManiphestStatusEmailCommand
  extends ManiphestEmailCommand {

  public function getCommand() {
    return 'status';
  }

  public function getCommandSyntax() {
    return '**!status** //status//';
  }

  public function getCommandSummary() {
    return pht('Change the status of a task.');
  }

  public function getCommandDescription() {
    $names = ManiphestTaskStatus::getTaskStatusMap();
    $keywords = ManiphestTaskStatus::getTaskStatusKeywordsMap();

    $table = array();
    $table[] = '| '.pht('Status').' | '.pht('Keywords');
    $table[] = '|---|---|';
    foreach ($keywords as $status => $words) {
      $words = implode(', ', $words);
      $table[] = '| '.$names[$status].' | '.$words;
    }
    $table = implode("\n", $table);

    return pht(
      "To change the status of a task, specify the desired status, like ".
      "`%s`. This table shows the configured names for statuses.\n\n%s\n\n".
      "If you specify an invalid status, the command is ignored. This ".
      "command has no effect if you do not specify a status.",
      '!status invalid',
      $table);
  }

  public function buildTransactions(
    PhabricatorUser $viewer,
    PhabricatorApplicationTransactionInterface $object,
    PhabricatorMetaMTAReceivedMail $mail,
    $command,
    array $argv) {
    $xactions = array();

    $target = phutil_utf8_strtolower(head($argv));
    $status = null;

    $keywords = ManiphestTaskStatus::getTaskStatusKeywordsMap();
    foreach ($keywords as $key => $words) {
      foreach ($words as $word) {
        if ($word == $target) {
          $status = $key;
          break;
        }
      }
    }

    if ($status === null) {
      return array();
    }

    $xactions[] = $object->getApplicationTransactionTemplate()
      ->setTransactionType(ManiphestTransaction::TYPE_STATUS)
      ->setNewValue($status);

    return $xactions;
  }

}
