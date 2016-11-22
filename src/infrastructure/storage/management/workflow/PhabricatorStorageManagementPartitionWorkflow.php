<?php

final class PhabricatorStorageManagementPartitionWorkflow
  extends PhabricatorStorageManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('partition')
      ->setExamples('**partition** [__options__]')
      ->setSynopsis(pht('Commit partition configuration to databases.'))
      ->setArguments(array());
  }

  public function didExecute(PhutilArgumentParser $args) {
    echo tsprintf(
      "%s\n",
      pht('Committing configured partition map to databases...'));

    foreach ($this->getMasterAPIs() as $api) {
      $ref = $api->getRef();
      $conn = $ref->newManagementConnection();

      $state = $ref->getPartitionStateForCommit();

      queryfx(
        $conn,
        'INSERT INTO %T.%T (stateKey, stateValue) VALUES (%s, %s)
          ON DUPLICATE KEY UPDATE stateValue = VALUES(stateValue)',
        $api->getDatabaseName('meta_data'),
        PhabricatorStorageManagementAPI::TABLE_HOSTSTATE,
        'cluster.databases',
        $state);

      echo tsprintf(
        "%s\n",
        pht(
          'Wrote configuration on database host "%s".',
          $ref->getRefKey()));
    }

    return 0;
  }

}
