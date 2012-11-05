<?php

final class PhabricatorStorageManagementDatabasesWorkflow
  extends PhabricatorStorageManagementWorkflow {

  public function didConstruct() {
    $this
      ->setName('databases')
      ->setExamples('**databases** [__options__]')
      ->setSynopsis('List Phabricator databases.');
  }

  public function execute(PhutilArgumentParser $args) {
    $api = $this->getAPI();
    $patches = $this->getPatches();

    $databases = $api->getDatabaseList($patches);
    echo implode("\n", $databases)."\n";

    return 0;
  }

}
