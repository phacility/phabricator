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
    $admin = PhabricatorUser::getOmnipotentUser();
    $peoplegen = new PhabricatorPeopleTestDataGenerator();
    $object = $peoplegen->generate();
    $handle = PhabricatorObjectHandleData::loadOneHandle($object->getPHID(),
      $admin);
    echo "Generated ".$handle->getFullName()."\n";
    echo "\nRequested data has been generated.";
  }

}
