<?php

/**
 * @task docs Command Documentation
 */
abstract class MetaMTAEmailTransactionCommand extends Phobject {

  abstract public function getCommand();

  /**
   * Return a brief human-readable description of the command effect.
   *
   * This should normally be one or two sentences briefly describing the
   * command behavior.
   *
   * @return string Brief human-readable remarkup.
   * @task docs
   */
  abstract public function getCommandSummary();


  /**
   * Return a one-line Remarkup description of command syntax for documentation.
   *
   * @return string Brief human-readable remarkup.
   * @task docs
   */
  public function getCommandSyntax() {
    return '**!'.$this->getCommand().'**';
  }

  /**
   * Return a longer human-readable description of the command effect.
   *
   * This can be as long as necessary to explain the command.
   *
   * @return string Human-readable remarkup of whatever length is desired.
   * @task docs
   */
  public function getCommandDescription() {
    return null;
  }

  abstract public function isCommandSupportedForObject(
    PhabricatorApplicationTransactionInterface $object);

  abstract public function buildTransactions(
    PhabricatorUser $viewer,
    PhabricatorApplicationTransactionInterface $object,
    PhabricatorMetaMTAReceivedMail $mail,
    $command,
    array $argv);

  public function getCommandAliases() {
    return array();
  }

  public function getCommandObjects() {
    return array($this);
  }

  public static function getAllCommands() {
    static $commands;

    if ($commands === null) {
      $kinds = id(new PhutilSymbolLoader())
        ->setAncestorClass(__CLASS__)
        ->loadObjects();
      $commands = array();
      foreach ($kinds as $kind) {
        foreach ($kind->getCommandObjects() as $command) {
          $commands[] = $command;
        }
      }
    }

    return $commands;
  }

  public static function getAllCommandsForObject(
    PhabricatorApplicationTransactionInterface $object) {

    $commands = self::getAllCommands();
    foreach ($commands as $key => $command) {
      if (!$command->isCommandSupportedForObject($object)) {
        unset($commands[$key]);
      }
    }

    return $commands;
  }

  public static function getCommandMap(array $commands) {
    assert_instances_of($commands, __CLASS__);

    $map = array();
    foreach ($commands as $command) {
      $keywords = $command->getCommandAliases();
      $keywords[] = $command->getCommand();

      foreach ($keywords as $keyword) {
        $keyword = phutil_utf8_strtolower($keyword);
        if (empty($map[$keyword])) {
          $map[$keyword] = $command;
        } else {
          throw new Exception(
            pht(
              'Mail commands "%s" and "%s" both respond to keyword "%s". '.
              'Keywords must be uniquely associated with commands.',
              get_class($command),
              get_class($map[$keyword]),
              $keyword));
        }
      }
    }

    return $map;
  }

}
