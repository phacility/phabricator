<?php

final class PhabricatorLipsumGenerateWorkflow
  extends PhabricatorLipsumManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('generate')
      ->setExamples('**generate**')
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
    $type = "Paste";
    $classname = "Phabricator".$type."TestDataGenerator";
    $admin = PhabricatorUser::getOmnipotentUser();
    try {
      $taskgen = newv($classname, array());
      $object = $taskgen->generate();

      $handle = PhabricatorObjectHandleData::loadOneHandle($object->getPHID(),
        $admin);
      echo "Generated ".$handle->getFullName()."\n";
      echo "\nRequested data has been generated.";
    } catch (PhutilMissingSymbolException $ex) {
    }


  }

}
