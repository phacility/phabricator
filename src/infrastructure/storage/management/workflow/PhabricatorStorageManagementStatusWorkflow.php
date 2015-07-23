<?php

final class PhabricatorStorageManagementStatusWorkflow
  extends PhabricatorStorageManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('status')
      ->setExamples('**status** [__options__]')
      ->setSynopsis(pht('Show patch application status.'));
  }

  public function execute(PhutilArgumentParser $args) {
    $api = $this->getAPI();
    $patches = $this->getPatches();

    $applied = $api->getAppliedPatches();

    if ($applied === null) {
      echo phutil_console_format(
        "**%s**: %s\n",
        pht('Database Not Initialized'),
        pht('Run **%s** to initialize.', 'storage upgrade'));

      return 1;
    }

    $table = id(new PhutilConsoleTable())
      ->setShowHeader(false)
      ->addColumn('id',     array('title' => pht('ID')))
      ->addColumn('status', array('title' => pht('Status')))
      ->addColumn('type',   array('title' => pht('Type')))
      ->addColumn('name',   array('title' => pht('Name')));

    foreach ($patches as $patch) {
      $table->addRow(array(
        'id' => $patch->getFullKey(),
        'status' => in_array($patch->getFullKey(), $applied)
          ? pht('Applied')
          : pht('Not Applied'),
        'type' => $patch->getType(),
        'name' => $patch->getName(),
      ));
    }

    $table->draw();
    return 0;
  }

}
