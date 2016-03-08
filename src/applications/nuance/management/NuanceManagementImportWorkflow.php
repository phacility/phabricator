<?php

final class NuanceManagementImportWorkflow
  extends NuanceManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('import')
      ->setExamples('**import** [__options__]')
      ->setSynopsis(pht('Import data from a source.'))
      ->setArguments(
        array(
          array(
            'name' => 'source',
            'param' => 'source',
            'help' => pht('Choose which source to import.'),
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

    foreach ($cursors as $cursor) {
      $cursor->importFromSource();
    }

    return 0;
  }

}
