<?php

final class PhabricatorLipsumGenerateWorkflow
  extends PhabricatorLipsumManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('generate')
      ->setExamples('**generate**')
      ->setSynopsis(pht('Generate synthetic test objects.'))
      ->setArguments(
        array(
          array(
            'name'      => 'args',
            'wildcard'  => true,
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $config_key = 'phabricator.developer-mode';
    if (!PhabricatorEnv::getEnvConfig($config_key)) {
      throw new PhutilArgumentUsageException(
        pht(
          'lipsum is a development and testing tool and may only be run '.
          'on installs in developer mode. Enable "%s" in your configuration '.
          'to enable lipsum.',
          $config_key));
    }

    $all_generators = id(new PhutilClassMapQuery())
      ->setAncestorClass('PhabricatorTestDataGenerator')
      ->execute();

    $argv = $args->getArg('args');
    $all = 'all';

    if (!$argv) {
      $names = mpull($all_generators, 'getGeneratorName');
      sort($names);

      $list = id(new PhutilConsoleList())
        ->setWrap(false)
        ->addItems($names);

      id(new PhutilConsoleBlock())
        ->addParagraph(
          pht(
            'Choose which type or types of test data you want to generate, '.
            'or select "%s".',
            $all))
        ->addList($list)
        ->draw();

      return 0;
    }

    $generators = array();
    foreach ($argv as $arg_original) {
      $arg = phutil_utf8_strtolower($arg_original);

      $match = false;
      foreach ($all_generators as $generator) {
        $name = phutil_utf8_strtolower($generator->getGeneratorName());

        if ($arg == $all) {
          $generators[] = $generator;
          $match = true;
          break;
        }

        if (strpos($name, $arg) !== false) {
          $generators[] = $generator;
          $match = true;
          break;
        }
      }

      if (!$match) {
        throw new PhutilArgumentUsageException(
          pht(
            'Argument "%s" does not match the name of any generators.',
            $arg_original));
      }
    }

    echo tsprintf(
      "**<bg:blue> %s </bg>** %s\n",
      pht('GENERATORS'),
      pht(
        'Selected generators: %s.',
        implode(', ', mpull($generators, 'getGeneratorName'))));

    echo tsprintf(
      "**<bg:yellow> %s </bg>** %s\n",
      pht('WARNING'),
      pht(
        'This command generates synthetic test data, including user '.
        'accounts. It is intended for use in development environments '.
        'so you can test features more easily. There is no easy way to '.
        'delete this data or undo the effects of this command. If you run '.
        'it in a production environment, it will pollute your data with '.
        'large amounts of meaningless garbage that you can not get rid of.'));

    $prompt = pht('Are you sure you want to generate piles of garbage?');
    if (!phutil_console_confirm($prompt, true)) {
      return;
    }

    echo tsprintf(
      "**<bg:green> %s </bg>** %s\n",
      pht('LIPSUM'),
      pht(
        'Generating synthetic test objects forever. '.
        'Use ^C to stop when satisfied.'));

    $this->generate($generators);
  }

  protected function generate(array $generators) {
    $viewer = $this->getViewer();

    foreach ($generators as $generator) {
      $generator->setViewer($this->getViewer());
    }

    while (true) {
      $generator = $generators[array_rand($generators)];

      try {
        $object = $generator->generateObject();
      } catch (Exception $ex) {
        echo tsprintf(
          "**<bg:yellow> %s </bg>** %s\n",
          pht('OOPS'),
          pht(
            'Generator ("%s") was unable to generate an object.',
            $generator->getGeneratorName()));

        echo tsprintf(
          "%B\n",
          $ex->getMessage());

        continue;
      }

      $object_phid = $object->getPHID();

      $handles = $viewer->loadHandles(array($object_phid));

      echo tsprintf(
        "%s\n",
        pht(
          'Generated "%s": %s',
          $handles[$object_phid]->getTypeName(),
          $handles[$object_phid]->getFullName()));

      sleep(1);
    }
  }

}
