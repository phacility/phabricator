<?php

final class PhabricatorStorageManagementStatusWorkflow
  extends PhabricatorStorageManagementWorkflow {

  public function didConstruct() {
    $this
      ->setName('status')
      ->setExamples('**status** [__options__]')
      ->setSynopsis('Show patch application status.');
  }

  public function execute(PhutilArgumentParser $args) {
    $api = $this->getAPI();
    $patches = $this->getPatches();

    $applied = $api->getAppliedPatches();

    if ($applied === null) {
      echo phutil_console_format(
        "**Database Not Initialized**: Run **storage upgrade** to ".
        "initialize.\n");

      return 1;
    }

    $table = id(new PhutilConsoleTable())
      ->setShowHeader(false)
      ->addColumn('id',     array('title' => 'ID'))
      ->addColumn('status', array('title' => 'Status'))
      ->addColumn('type',   array('title' => 'Type'))
      ->addColumn('name',   array('title' => 'Name'));

    foreach ($patches as $patch) {
      $table->addRow(array(
        'id' => $patch->getFullKey(),
        'status' => in_array($patch->getFullKey(), $applied)
          ? 'Applied'
          : 'Not Applied',
        'type' => $patch->getType(),
        'name' => $patch->getName(),
      ));
    }

    $table->draw();
    return 0;
  }

}
