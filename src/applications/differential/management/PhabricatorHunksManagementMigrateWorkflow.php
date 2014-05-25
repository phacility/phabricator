<?php

final class PhabricatorHunksManagementMigrateWorkflow
  extends PhabricatorHunksManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('migrate')
      ->setExamples('**migrate**')
      ->setSynopsis(pht('Migrate hunks to modern storage.'))
      ->setArguments(array());
  }

  public function execute(PhutilArgumentParser $args) {
    $saw_any_rows = false;
    $console = PhutilConsole::getConsole();

    $table = new DifferentialHunkLegacy();
    foreach (new LiskMigrationIterator($table) as $hunk) {
      $saw_any_rows = true;

      $id = $hunk->getID();
      $console->writeOut("%s\n", pht('Migrating hunk %d...', $id));

      $new_hunk = id(new DifferentialHunkModern())
        ->setChangesetID($hunk->getChangesetID())
        ->setOldOffset($hunk->getOldOffset())
        ->setOldLen($hunk->getOldLen())
        ->setNewOffset($hunk->getNewOffset())
        ->setNewLen($hunk->getNewLen())
        ->setChanges($hunk->getChanges())
        ->setDateCreated($hunk->getDateCreated())
        ->setDateModified($hunk->getDateModified());

      $hunk->openTransaction();
        $new_hunk->save();
        $hunk->delete();
      $hunk->saveTransaction();
    }

    if ($saw_any_rows) {
      $console->writeOut("%s\n", pht('Done.'));
    } else {
      $console->writeOut("%s\n", pht('No rows to migrate.'));
    }
  }

}
