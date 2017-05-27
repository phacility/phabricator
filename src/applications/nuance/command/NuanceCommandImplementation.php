<?php

abstract class NuanceCommandImplementation
  extends Phobject {

  private $actor;

  private $transactionQueue = array();

  final public function setActor(PhabricatorUser $actor) {
    $this->actor = $actor;
    return $this;
  }

  final public function getActor() {
    return $this->actor;
  }

  abstract public function getCommandName();
  abstract public function canApplyToItem(NuanceItem $item);

  public function canApplyImmediately(
    NuanceItem $item,
    NuanceItemCommand $command) {
    return false;
  }

  abstract protected function executeCommand(
    NuanceItem $item,
    NuanceItemCommand $command);

  final public function applyCommand(
    NuanceItem $item,
    NuanceItemCommand $command) {

    $command_key = $command->getCommand();
    $implementation_key = $this->getCommandKey();
    if ($command_key !== $implementation_key) {
      throw new Exception(
        pht(
          'This command implementation("%s") can not apply a command of a '.
          'different type ("%s").',
          $implementation_key,
          $command_key));
    }

    if (!$this->canApplyToItem($item)) {
      throw new Exception(
        pht(
          'This command implementation ("%s") can not be applied to an '.
          'item of type "%s".',
          $implementation_key,
          $item->getItemType()));
    }

    $this->transactionQueue = array();

    $command_type = NuanceItemCommandTransaction::TRANSACTIONTYPE;
    $command_xaction = $this->newTransaction($command_type);

    $result = $this->executeCommand($item, $command);

    $xactions = $this->transactionQueue;
    $this->transactionQueue = array();

    $command_xaction->setNewValue(
      array(
        'command' => $command->getCommand(),
        'parameters' => $command->getParameters(),
        'result' => $result,
      ));

    // TODO: Maybe preserve the actor's original content source?
    $source = PhabricatorContentSource::newForSource(
      PhabricatorDaemonContentSource::SOURCECONST);

    $actor = $this->getActor();

    id(new NuanceItemEditor())
      ->setActor($actor)
      ->setActingAsPHID($command->getAuthorPHID())
      ->setContentSource($source)
      ->setContinueOnMissingFields(true)
      ->setContinueOnNoEffect(true)
      ->applyTransactions($item, $xactions);
  }

  final public function getCommandKey() {
    return $this->getPhobjectClassConstant('COMMANDKEY');
  }

  final public static function getAllCommands() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getCommandKey')
      ->execute();
  }

  protected function newTransaction($type) {
    $xaction = id(new NuanceItemTransaction())
      ->setTransactionType($type);

    $this->transactionQueue[] = $xaction;

    return $xaction;
  }

  protected function newStatusTransaction($status) {
    return $this->newTransaction(NuanceItemStatusTransaction::TRANSACTIONTYPE)
      ->setNewValue($status);
  }

}
