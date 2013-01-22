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

    $options = PhabricatorApplicationConfigOptions::loadAllOptions();
    if (empty($options[$key])) {
      throw new PhutilArgumentUsageException(
        "No such configuration key '{$key}'! Use `config list` to list all ".
        "keys.");
    }

    $option = $options[$key];

    $type = $option->getType();
    switch ($type) {
      case 'string':
      case 'class':
      case 'enum':
        $value = (string)$value;
        break;
      case 'int':
        if (!ctype_digit($value)) {
          throw new PhutilArgumentUsageException(
            "Config key '{$key}' is of type '{$type}'. Specify an integer.");
        }
        $value = (int)$value;
        break;
      case 'bool':
        if ($value == 'true') {
          $value = true;
        } else if ($value == 'false') {
          $value = false;
        } else {
          throw new PhutilArgumentUsageException(
            "Config key '{$key}' is of type '{$type}'. ".
            "Specify 'true' or 'false'.");
        }
        break;
      default:
        $value = json_decode($value, true);
        if (!is_array($value)) {
          throw new PhutilArgumentUsageException(
            "Config key '{$key}' is of type '{$type}'. Specify it in JSON.");
        }
        break;
    }

    try {
      $option->getGroup()->validateOption($option, $value);
    } catch (PhabricatorConfigValidationException $validation) {
      // Convert this into a usage exception so we don't dump a stack trace.
      throw new PhutilArgumentUsageException($validation->getMessage());
    }

    $config = new PhabricatorConfigLocalSource();
    $config->setKeys(array($key => $value));

    $console->writeOut(
      pht("Set '%s' in local configuration.", $key)."\n");
  }

}
