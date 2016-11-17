<?php

final class PhabricatorStorageManagementAdjustWorkflow
  extends PhabricatorStorageManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('adjust')
      ->setExamples('**adjust** [__options__]')
      ->setSynopsis(
        pht(
          'Make schemata adjustments to correct issues with characters sets, '.
          'collations, and keys.'))
      ->setArguments(
        array(
          array(
            'name' => 'unsafe',
            'help' => pht(
              'Permit adjustments which truncate data. This option may '.
              'destroy some data, but the lost data is usually not '.
              'important (most commonly, the ends of very long object '.
              'titles).'),
          ),
        ));
  }

  public function didExecute(PhutilArgumentParser $args) {
    $unsafe = $args->getArg('unsafe');

    foreach ($this->getMasterAPIs() as $api) {
      $this->requireAllPatchesApplied($api);
      $err = $this->adjustSchemata($api, $unsafe);
      if ($err) {
        return $err;
      }
    }

    return 0;
  }

  private function requireAllPatchesApplied(
    PhabricatorStorageManagementAPI $api) {
    $applied = $api->getAppliedPatches();

    if ($applied === null) {
      throw new PhutilArgumentUsageException(
        pht(
          'You have not initialized the database yet. You must initialize '.
          'the database before you can adjust schemata. Run `%s` '.
          'to initialize the database.',
          'storage upgrade'));
    }

    $applied = array_fuse($applied);

    $patches = $this->getPatches();
    $patches = mpull($patches, null, 'getFullKey');
    $missing = array_diff_key($patches, $applied);

    if ($missing) {
      throw new PhutilArgumentUsageException(
        pht(
          'You have not applied all available storage patches yet. You must '.
          'apply all available patches before you can adjust schemata. '.
          'Run `%s` to show patch status, and `%s` to apply missing patches.',
          'storage status',
          'storage upgrade'));
    }
  }

}
