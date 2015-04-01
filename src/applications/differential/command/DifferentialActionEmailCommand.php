<?php

final class DifferentialActionEmailCommand
  extends MetaMTAEmailTransactionCommand {

  private $command;
  private $action;
  private $aliases;

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

  public function getCommandObjects() {
    $actions = array(
      DifferentialAction::ACTION_REJECT => 'request',
      DifferentialAction::ACTION_ABANDON => 'abandon',
      DifferentialAction::ACTION_RECLAIM => 'reclaim',
      DifferentialAction::ACTION_RESIGN => 'resign',
      DifferentialAction::ACTION_RETHINK => 'rethink',
      DifferentialAction::ACTION_CLAIM => 'commandeer',
    );

    if (PhabricatorEnv::getEnvConfig('differential.enable-email-accept')) {
      $actions[DifferentialAction::ACTION_ACCEPT] = 'accept';
    }

    $aliases = array(
      DifferentialAction::ACTION_REJECT => array('reject'),
      DifferentialAction::ACTION_CLAIM => array('claim'),
    );

    $objects = array();
    foreach ($actions as $action => $keyword) {
      $object = id(new DifferentialActionEmailCommand())
        ->setCommand($keyword)
        ->setAction($action);

      if (isset($aliases[$action])) {
        $object->setCommandAliases($aliases[$action]);
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
