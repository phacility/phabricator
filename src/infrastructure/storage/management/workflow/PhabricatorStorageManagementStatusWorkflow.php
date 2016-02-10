<?php

final class PhabricatorStorageManagementStatusWorkflow
  extends PhabricatorStorageManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('status')
      ->setExamples('**status** [__options__]')
      ->setSynopsis(pht('Show patch application status.'));
  }

  public function didExecute(PhutilArgumentParser $args) {
    $api     = $this->getAPI();
    $patches = $this->getPatches();

    $applied = $api->getAppliedPatches();

    if ($applied === null) {
      echo phutil_console_format(
        "**%s**: %s\n",
        pht('Database Not Initialized'),
        pht('Run **%s** to initialize.', './bin/storage upgrade'));

      return 1;
    }

    $table = id(new PhutilConsoleTable())
      ->setShowHeader(false)
      ->addColumn('id',       array('title' => pht('ID')))
      ->addColumn('status',   array('title' => pht('Status')))
      ->addColumn('duration', array('title' => pht('Duration')))
      ->addColumn('type',     array('title' => pht('Type')))
      ->addColumn('name',     array('title' => pht('Name')));

    $durations = $api->getPatchDurations();

    foreach ($patches as $patch) {
      $duration = idx($durations, $patch->getFullKey());
      if ($duration === null) {
        $duration = '-';
      } else {
        $duration = pht('%s us', new PhutilNumber($duration));
      }

      $table->addRow(array(
        'id' => $patch->getFullKey(),
        'status' => in_array($patch->getFullKey(), $applied)
          ? pht('Applied')
          : pht('Not Applied'),
        'duration' => $duration,
        'type' => $patch->getType(),
        'name' => $patch->getName(),
      ));
    }

    $table->draw();
    return 0;
  }

}
