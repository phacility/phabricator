<?php

final class PhabricatorConfigManagementGetWorkflow
  extends PhabricatorConfigManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('get')
      ->setExamples('**get** __key__')
      ->setSynopsis('Get a local configuration value.')
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
        "Specify a configuration key to get.");
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

    $result = array();
    foreach ($values as $key => $value) {
      $result[] = array(
        'key' => $key,
        'source' => 'local',
        'value' => $value,
      );
    }
    $result = array(
      'config' => $result,
    );

    $json = new PhutilJSON();
    $console->writeOut($json->encodeFormatted($result));
  }

}
