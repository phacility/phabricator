<?php

final class DifferentialActionEmailCommand
  extends MetaMTAEmailTransactionCommand {

  private $command;
  private $action;
  private $aliases;
  private $commandSummary;
  private $commandDescription;

  public function getCommand() {
    return $this->command;
  }

  private function setCommand($command) {
    $this->command = $command;
    return $this;
  }

  private function setAction($action) {
    $this->action = $action;
    return $this;
  }

  private function getAction() {
    return $this->action;
  }

  private function setCommandAliases(array $aliases) {
    $this->aliases = $aliases;
    return $this;
  }

  public function getCommandAliases() {
    return $this->aliases;
  }

  public function setCommandSummary($command_summary) {
    $this->commandSummary = $command_summary;
    return $this;
  }

  public function getCommandSummary() {
    return $this->commandSummary;
  }

  public function setCommandDescription($command_description) {
    $this->commandDescription = $command_description;
    return $this;
  }

  public function getCommandDescription() {
    return $this->commandDescription;
  }

  public function getCommandObjects() {
    $actions = DifferentialRevisionActionTransaction::loadAllActions();
    $actions = msort($actions, 'getRevisionActionOrderVector');

    $objects = array();
    foreach ($actions as $action) {
      $keyword = $action->getCommandKeyword();
      if ($keyword === null) {
        continue;
      }

      $aliases = $action->getCommandAliases();
      $summary = $action->getCommandSummary();

      $object = id(new self())
        ->setCommand($keyword)
        ->setCommandAliases($aliases)
        ->setAction($action->getTransactionTypeConstant())
        ->setCommandSummary($summary);

      $objects[] = $object;
    }

    return $objects;
  }

  public function isCommandSupportedForObject(
    PhabricatorApplicationTransactionInterface $object) {
    return ($object instanceof DifferentialRevision);
  }

  public function buildTransactions(
    PhabricatorUser $viewer,
    PhabricatorApplicationTransactionInterface $object,
    PhabricatorMetaMTAReceivedMail $mail,
    $command,
    array $argv) {
    $xactions = array();

    $xactions[] = $object->getApplicationTransactionTemplate()
      ->setTransactionType($this->getAction())
      ->setNewValue(true);

    return $xactions;
  }

}
