<?php

final class PhabricatorDifferentialMigrateHunkWorkflow
  extends PhabricatorDifferentialManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('migrate-hunk')
      ->setExamples('**migrate-hunk** --id __hunk__ --to __storage__')
      ->setSynopsis(pht('Migrate storage engines for a hunk.'))
      ->setArguments(
        array(
          array(
            'name' => 'id',
            'param' => 'id',
            'help' => pht('Hunk ID to migrate.'),
          ),
          array(
            'name' => 'to',
            'param' => 'storage',
            'help' => pht('Storage engine to migrate to.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $id = $args->getArg('id');
    if (!$id) {
      throw new PhutilArgumentUsageException(
        pht('Specify a hunk to migrate with --id.'));
    }

    $storage = $args->getArg('to');
    switch ($storage) {
      case DifferentialModernHunk::DATATYPE_TEXT:
      case DifferentialModernHunk::DATATYPE_FILE:
        break;
      default:
        throw new PhutilArgumentUsageException(
          pht('Specify a hunk storage engine with --to.'));
    }

    $hunk = $this->loadHunk($id);
    $old_data = $hunk->getChanges();

    switch ($storage) {
      case DifferentialModernHunk::DATATYPE_TEXT:
        $hunk->saveAsText();
        $this->logOkay(
          pht('TEXT'),
          pht('Convereted hunk to text storage.'));
        break;
      case DifferentialModernHunk::DATATYPE_FILE:
        $hunk->saveAsFile();
        $this->logOkay(
          pht('FILE'),
          pht('Convereted hunk to file storage.'));
        break;
    }

    $hunk = $this->loadHunk($id);
    $new_data = $hunk->getChanges();

    if ($old_data !== $new_data) {
      throw new Exception(
        pht(
          'Integrity check failed: new file data differs fom old data!'));
    }

    return 0;
  }

  private function loadHunk($id) {
    $hunk = id(new DifferentialModernHunk())->load($id);
    if (!$hunk) {
      throw new PhutilArgumentUsageException(
        pht(
          'No hunk exists with ID "%s".',
          $id));
    }

    return $hunk;
  }


}
