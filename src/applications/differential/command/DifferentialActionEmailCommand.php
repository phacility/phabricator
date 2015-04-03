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
    $actions = array(
      DifferentialAction::ACTION_REJECT => 'request',
      DifferentialAction::ACTION_ABANDON => 'abandon',
      DifferentialAction::ACTION_RECLAIM => 'reclaim',
      DifferentialAction::ACTION_RESIGN => 'resign',
      DifferentialAction::ACTION_RETHINK => 'planchanges',
      DifferentialAction::ACTION_CLAIM => 'commandeer',
    );

    if (PhabricatorEnv::getEnvConfig('differential.enable-email-accept')) {
      $actions[DifferentialAction::ACTION_ACCEPT] = 'accept';
    }

    $aliases = array(
      DifferentialAction::ACTION_REJECT => array('reject'),
      DifferentialAction::ACTION_CLAIM => array('claim'),
      DifferentialAction::ACTION_RETHINK => array('rethink'),
    );

    $summaries = array(
      DifferentialAction::ACTION_REJECT =>
        pht('Request changes to a revision.'),
      DifferentialAction::ACTION_ABANDON =>
        pht('Abandon a revision.'),
      DifferentialAction::ACTION_RECLAIM =>
        pht('Reclaim a revision.'),
      DifferentialAction::ACTION_RESIGN =>
        pht('Resign from a revision.'),
      DifferentialAction::ACTION_RETHINK =>
        pht('Plan changes to a revision.'),
      DifferentialAction::ACTION_CLAIM =>
        pht('Commandeer a revision.'),
      DifferentialAction::ACTION_ACCEPT =>
        pht('Accept a revision.'),
    );

    $descriptions = array(

    );

    $objects = array();
    foreach ($actions as $action => $keyword) {
      $object = id(new DifferentialActionEmailCommand())
        ->setCommand($keyword)
        ->setAction($action)
        ->setCommandSummary($summaries[$action]);

      if (isset($aliases[$action])) {
        $object->setCommandAliases($aliases[$action]);
      }

      if (isset($descriptions[$action])) {
        $object->setCommandDescription($descriptions[$action]);
      }

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
      ->setTransactionType(DifferentialTransaction::TYPE_ACTION)
      ->setNewValue($this->getAction());

    return $xactions;
  }

}
