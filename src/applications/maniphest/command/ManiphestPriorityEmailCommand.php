<?php

final class ManiphestPriorityEmailCommand
  extends ManiphestEmailCommand {

  public function getCommand() {
    return 'priority';
  }

  public function getCommandSyntax() {
    return '**!priority** //priority//';
  }

  public function getCommandSummary() {
    return pht('Change the priority of a task.');
  }

  public function getCommandDescription() {
    $names = ManiphestTaskPriority::getTaskPriorityMap();
    $keywords = ManiphestTaskPriority::getTaskPriorityKeywordsMap();

    $table = array();
    $table[] = '| '.pht('Priority').' | '.pht('Keywords');
    $table[] = '|---|---|';
    foreach ($keywords as $priority => $words) {
      if (ManiphestTaskPriority::isDisabledPriority($priority)) {
        continue;
      }
      $words = implode(', ', $words);
      $table[] = '| '.$names[$priority].' | '.$words;
    }
    $table = implode("\n", $table);

    return pht(
      "To change the priority of a task, specify the desired priority, like ".
      "`%s`. This table shows the configured names for priority levels.".
      "\n\n%s\n\n".
      "If you specify an invalid priority, the command is ignored. This ".
      "command has no effect if you do not specify a priority.",
      '!priority high',
      $table);
  }

  public function buildTransactions(
    PhabricatorUser $viewer,
    PhabricatorApplicationTransactionInterface $object,
    PhabricatorMetaMTAReceivedMail $mail,
    $command,
    array $argv) {
    $xactions = array();

    $keyword = phutil_utf8_strtolower(head($argv));
    $priority = ManiphestTaskPriority::getTaskPriorityFromKeyword($keyword);

    if ($priority === null) {
      return array();
    }

    if (ManiphestTaskPriority::isDisabledPriority($priority)) {
      return array();
    }

    $xactions[] = $object->getApplicationTransactionTemplate()
      ->setTransactionType(ManiphestTaskPriorityTransaction::TRANSACTIONTYPE)
      ->setNewValue($keyword);

    return $xactions;
  }

}
