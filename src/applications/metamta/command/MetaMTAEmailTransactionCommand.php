<?php

abstract class MetaMTAEmailTransactionCommand extends Phobject {

  abstract public function getCommand();
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
    assert_instances_of($commands, 'MetaMTAEmailTransactionCommand');

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
