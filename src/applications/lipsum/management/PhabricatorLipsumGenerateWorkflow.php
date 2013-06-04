<?php

final class PhabricatorLipsumGenerateWorkflow
  extends PhabricatorLipsumManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('generate')
      ->setExamples('**generate** __key__')
      ->setSynopsis('Generate some lipsum.')
      ->setArguments(
        array(
          array(
            'name'      => 'args',
            'wildcard'  => true,
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $supported_types = id(new PhutilSymbolLoader())
      ->setAncestorClass('PhabricatorTestDataGenerator')
      ->loadObjects();
    echo "These are the types of data you can generate:\n";
    foreach (array_keys($supported_types) as $typetmp) {
      echo "\t".$typetmp."\n";
    }
    echo "\n";
    $prompt = "Are you sure you want to generate lots of test data?";
    if (!phutil_console_confirm($prompt, $default_no = true)) {
      return;
    }
    $argv = $args->getArg('args');
    if (count($argv) == 0 ||
      (count($argv) == 1 && $argv[0] == "all")) {
      $this->infinitelyGenerate($supported_types);
    } else {
      $new_supported_types = array();
      for ($i = 0; $i < count($argv);$i++) {
        $arg = $argv[$i];
        if (array_key_exists($arg, $supported_types)) {
           $new_supported_types[$arg] = $supported_types[$arg];
        } else {
          echo "The type ".$arg." is not supported by the lipsum generator.\n";
        }
      }
      $this->infinitelyGenerate($new_supported_types);
    }
    echo "None of the input types were supported.\n";
    echo "The supported types are:\n";
    echo implode("\n", array_keys($supported_types));
  }

  protected function infinitelyGenerate(array $supported_types) {
    if (count($supported_types) == 0) {
      echo "None of the input types were supported.\n";
      return;
    }
    echo "GENERATING: ";
    echo strtoupper(implode(" , ", array_keys($supported_types)));
    echo "\n";
    while (true) {
      $type = $supported_types[array_rand($supported_types)];
      $admin = PhabricatorUser::getOmnipotentUser();
      try {
        $taskgen = newv($type, array());
        $object = $taskgen->generate();
        $handle = PhabricatorObjectHandleData::loadOneHandle($object->getPHID(),
          $admin);
        echo "Generated ".$handle->getTypeName().": ".
          $handle->getFullName()."\n";
      } catch (PhutilMissingSymbolException $ex) {
        throw new PhutilArgumentUsageException(
        "Cannot generate content of type ".$type.
        " because the class does not exist.");
      }
      usleep(200000);
    }
  }

}
