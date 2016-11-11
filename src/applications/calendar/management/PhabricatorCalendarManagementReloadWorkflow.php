<?php

final class PhabricatorCalendarManagementReloadWorkflow
  extends PhabricatorCalendarManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('reload')
      ->setExamples('**reload** [options] __id__ ...')
      ->setSynopsis(
        pht(
          'Reload event imports from the command line. Useful for '.
          'testing and debugging importers.'))
      ->setArguments(
        array(
          array(
            'name' => 'ids',
            'wildcard' => true,
            'help' => pht('List of import IDs to reload.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $viewer = $this->getViewer();

    $ids = $args->getArg('ids');
    if (!$ids) {
      throw new PhutilArgumentUsageException(
        pht('Specify at least one import ID to reload.'));
    }

    $imports = id(new PhabricatorCalendarImportQuery())
      ->setViewer($viewer)
      ->withIDs($ids)
      ->execute();
    $imports = mpull($imports, null, 'getID');
    foreach ($ids as $id) {
      if (empty($imports[$id])) {
        throw new PhutilArgumentUsageException(
          pht(
            'Unable to load Calendar import with ID "%s".',
            $id));
      }
    }

    $imports = array_select_keys($imports, $ids);

    foreach ($imports as $import) {
      echo tsprintf(
        "%s\n",
        pht(
          'Importing "%s"...',
          $import->getDisplayName()));

      $engine = $import->getEngine();

      $engine->importEventsFromSource($viewer, $import, false);
    }

    echo tsprintf(
      "%s\n",
      pht('Done.'));

    return 0;
  }

}
