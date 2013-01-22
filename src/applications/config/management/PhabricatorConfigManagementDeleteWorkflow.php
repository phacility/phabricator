<?php

final class PhabricatorConfigManagementDeleteWorkflow
  extends PhabricatorConfigManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('delete')
      ->setExamples('**delete** __key__')
      ->setSynopsis('Delete a local configuration value.')
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
        "Specify a configuration key to delete.");
    }

    $key = $argv[0];

    if (count($argv) > 1) {
      throw new PhutilArgumentUsageException(
        "Too many arguments: expected one key.");
    }

    $options = PhabricatorApplicationConfigOptions::loadAllOptions();
    if (empty($options[$key])) {
      throw new PhutilArgumentUsageException(
        "No such configuration key '{$key}'! Use `config list` to list all ".
        "keys.");
    }

    $config = new PhabricatorConfigLocalSource();
    $values = $config->getKeys(array($key));
    if (!$values) {
      throw new PhutilArgumentUsageException(
        "Configuration key '{$key}' is not set in local configuration!");
    }

    $config->deleteKeys(array($key));

    $console->writeOut(
      pht("Deleted '%s' from local configuration.", $key)."\n");
  }

}
