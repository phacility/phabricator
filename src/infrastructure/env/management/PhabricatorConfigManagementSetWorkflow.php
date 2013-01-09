<?php

final class PhabricatorConfigManagementSetWorkflow
  extends PhabricatorConfigManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('set')
      ->setExamples('**set** __key__ __value__')
      ->setSynopsis('Set a local configuration value.')
      ->setArguments(
        array(
          array(
            'name'      => 'args',
            'wildcard'  => true,
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();

    $argv = $args->getArg('args');
    if (count($argv) == 0) {
      throw new PhutilArgumentUsageException(
        "Specify a configuration key and a value to set it to.");
    }

    $key = $argv[0];

    if (count($argv) == 1) {
      throw new PhutilArgumentUsageException(
        "Specify a value to set the key '{$key}' to.");
    }

    $value = $argv[1];

    if (count($argv) > 2) {
      throw new PhutilArgumentUsageException(
        "Too many arguments: expected one key and one value.");
    }

    $config = new PhabricatorConfigLocalSource();
    $config->setKeys(array($key => $value));

    $console->writeOut(
      pht("Set '%s' to '%s' in local configuration.", $key, $value)."\n");
  }

}
