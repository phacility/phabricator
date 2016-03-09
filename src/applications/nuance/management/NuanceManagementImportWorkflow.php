<?php

final class NuanceManagementImportWorkflow
  extends NuanceManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('import')
      ->setExamples('**import** --source __source__ [__options__]')
      ->setSynopsis(pht('Import data from a source.'))
      ->setArguments(
        array(
          array(
            'name' => 'source',
            'param' => 'source',
            'help' => pht('Choose which source to import.'),
          ),
          array(
            'name' => 'cursor',
            'param' => 'cursor',
            'help' => pht('Import only a particular cursor.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $source = $this->loadSource($args, 'source');

    $definition = $source->getDefinition()
      ->setViewer($this->getViewer())
      ->setSource($source);

    if (!$definition->hasImportCursors()) {
      throw new PhutilArgumentUsageException(
        pht(
          'This source ("%s") does not expose import cursors.',
          $source->getName()));
    }

    $cursors = $definition->getImportCursors();
    if (!$cursors) {
      throw new PhutilArgumentUsageException(
        pht(
          'This source ("%s") does not have any import cursors.',
          $source->getName()));
    }

    $select = $args->getArg('cursor');
    if (strlen($select)) {
      if (empty($cursors[$select])) {
        throw new PhutilArgumentUsageException(
          pht(
            'This source ("%s") does not have a "%s" cursor. Available '.
            'cursors: %s.',
            $source->getName(),
            $select,
            implode(', ', array_keys($cursors))));
      } else {
        echo tsprintf(
          "%s\n",
          pht(
            'Importing cursor "%s" only.',
            $select));
        $cursors = array_select_keys($cursors, array($select));
      }
    } else {
      echo tsprintf(
        "%s\n",
        pht(
          'Importing all cursors: %s.',
          implode(', ', array_keys($cursors))));

      echo tsprintf(
        "%s\n",
        pht('(Use --cursor to import only a particular cursor.)'));
    }

    foreach ($cursors as $cursor) {
      $cursor->importFromSource();
    }

    return 0;
  }

}
