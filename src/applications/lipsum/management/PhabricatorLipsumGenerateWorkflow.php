<?php

final class PhabricatorLipsumGenerateWorkflow
  extends PhabricatorLipsumManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('generate')
      ->setExamples('**generate**')
      ->setSynopsis(pht('Generate some lipsum.'))
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

    $supported_types = id(new PhutilSymbolLoader())
      ->setAncestorClass('PhabricatorTestDataGenerator')
      ->loadObjects();

    $console->writeOut(
      "%s:\n\t%s\n",
      pht('These are the types of data you can generate'),
      implode("\n\t", array_keys($supported_types)));

    $prompt = pht('Are you sure you want to generate lots of test data?');
    if (!phutil_console_confirm($prompt, true)) {
      return;
    }

    $argv = $args->getArg('args');
    if (count($argv) == 0 || (count($argv) == 1 && $argv[0] == 'all')) {
      $this->infinitelyGenerate($supported_types);
    } else {
      $new_supported_types = array();
      for ($i = 0; $i < count($argv); $i++) {
        $arg = $argv[$i];
        if (array_key_exists($arg, $supported_types)) {
          $new_supported_types[$arg] = $supported_types[$arg];
        } else {
          $console->writeErr(
            "%s\n",
            pht(
              'The type %s is not supported by the lipsum generator.',
              $arg));
        }
      }
      $this->infinitelyGenerate($new_supported_types);
    }

    $console->writeOut(
      "%s\n%s:\n%s\n",
      pht('None of the input types were supported.'),
      pht('The supported types are'),
      implode("\n", array_keys($supported_types)));
  }

  protected function infinitelyGenerate(array $supported_types) {
    $console = PhutilConsole::getConsole();

    if (count($supported_types) == 0) {
      return;
    }
    $console->writeOut(
      "%s: %s\n",
      pht('GENERATING'),
      implode(', ', array_keys($supported_types)));

    while (true) {
      $type = $supported_types[array_rand($supported_types)];
      $admin = $this->getViewer();

      $taskgen = newv($type, array());
      $object = $taskgen->generate();
      $handle = id(new PhabricatorHandleQuery())
        ->setViewer($admin)
        ->withPHIDs(array($object->getPHID()))
        ->executeOne();

      $console->writeOut(
        "%s: %s\n",
        pht('Generated %s', $handle->getTypeName()),
        $handle->getFullName());

      usleep(200000);
    }
  }

}
