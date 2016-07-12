<?php

final class PhabricatorConfigManagementGetWorkflow
  extends PhabricatorConfigManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('get')
      ->setExamples('**get** __key__')
      ->setSynopsis(pht('Get a local configuration value.'))
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
        pht('Specify a configuration key to get.'));
    }

    $key = $argv[0];

    if (count($argv) > 1) {
      throw new PhutilArgumentUsageException(
        pht('Too many arguments: expected one key.'));
    }

    $options = PhabricatorApplicationConfigOptions::loadAllOptions();
    if (empty($options[$key])) {
      throw new PhutilArgumentUsageException(
        pht(
          "No such configuration key '%s'! Use `%s` to list all keys.",
          $key,
          'config list'));
    }

    $values = array();
    $config = new PhabricatorConfigLocalSource();
    $local_value = $config->getKeys(array($key));
    if (empty($local_value)) {
      $values['local'] = array(
        'key' => $key,
        'value' => null,
        'status' => 'unset',
        'errorInfo' => null,
      );
    } else {
      $values['local'] = array(
        'key' => $key,
        'value' => reset($local_value),
        'status' => 'set',
        'errorInfo' => null,
      );
    }

    try {
      $database_config = new PhabricatorConfigDatabaseSource('default');
      $database_value = $database_config->getKeys(array($key));
      if (empty($database_value)) {
        $values['database'] = array(
          'key' => $key,
          'value' => null,
          'status' => 'unset',
          'errorInfo' => null,
        );
      } else {
        $values['database'] = array(
          'key' => $key,
          'value' => reset($database_value),
          'status' => 'set',
          'errorInfo' => null,
        );
      }
    } catch (Exception $e) {
      $values['database'] = array(
        'key' => $key,
        'value' => null,
        'status' => 'error',
        'errorInfo' => pht('Database source is not configured properly'),
      );
    }

    $result = array();
    foreach ($values as $source => $value) {
      $result[] = array(
        'key' => $value['key'],
        'source' => $source,
        'value' => $value['value'],
        'status' => $value['status'],
        'errorInfo' => $value['errorInfo'],
      );
    }
    $result = array(
      'config' => $result,
    );

    $json = new PhutilJSON();
    $console->writeOut($json->encodeFormatted($result));
  }

}
