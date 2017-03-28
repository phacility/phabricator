<?php

final class PhabricatorSearchManagementInitWorkflow
  extends PhabricatorSearchManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('init')
      ->setSynopsis(pht('Initialize or repair a search service.'))
      ->setExamples('**init**');
  }

  public function execute(PhutilArgumentParser $args) {
    $this->validateClusterSearchConfig();

    $work_done = false;
    foreach (PhabricatorSearchService::getAllServices() as $service) {
      echo tsprintf(
        "%s\n",
        pht(
          'Initializing search service "%s".',
          $service->getDisplayName()));

      if (!$service->isWritable()) {
        echo tsprintf(
          "%s\n",
          pht(
            'Skipping service "%s" because it is not writable.',
            $service->getDisplayName()));
        continue;
      }

      $engine = $service->getEngine();

      if (!$engine->indexExists()) {
        echo tsprintf(
          "%s\n",
          pht('Service index does not exist, creating...'));

        $engine->initIndex();
        $work_done = true;
      } else if (!$engine->indexIsSane()) {
        echo tsprintf(
          "%s\n",
          pht('Service index is out of date, repairing...'));

        $engine->initIndex();
        $work_done = true;
      } else {
        echo tsprintf(
          "%s\n",
          pht('Service index is already up to date.'));
      }

      echo tsprintf(
        "%s\n",
        pht('Done.'));
    }

    if (!$work_done) {
      echo tsprintf(
        "%s\n",
        pht('No services need initialization.'));
      return 0;
    }

    echo tsprintf(
      "%s\n",
      pht('Service initialization complete.'));
  }
}
