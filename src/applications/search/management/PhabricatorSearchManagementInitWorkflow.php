<?php

final class PhabricatorSearchManagementInitWorkflow
  extends PhabricatorSearchManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('init')
      ->setSynopsis(pht('Initialize or repair an index.'))
      ->setExamples('**init**');
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();

    $work_done = false;
    foreach (PhabricatorSearchService::getAllServices() as $service) {
      $console->writeOut(
        "%s\n",
        pht('Initializing search service "%s"', $service->getDisplayName()));

      try {
        $host = $service->getAnyHostForRole('write');
      } catch (PhabricatorClusterNoHostForRoleException $e) {
        // If there are no writable hosts for a given cluster, skip it
        $console->writeOut("%s\n", $e->getMessage());
        continue;
      }

      $engine = $host->getEngine();

      if (!$engine->indexExists()) {
        $console->writeOut(
          '%s',
          pht('Index does not exist, creating...'));
        $engine->initIndex();
        $console->writeOut(
          "%s\n",
          pht('done.'));
        $work_done = true;
      } else if (!$engine->indexIsSane()) {
        $console->writeOut(
          '%s',
          pht('Index exists but is incorrect, fixing...'));
        $engine->initIndex();
        $console->writeOut(
          "%s\n",
          pht('done.'));
        $work_done = true;
      }
    }

    if ($work_done) {
      $console->writeOut(
        "%s\n",
        pht(
          'Index maintenance complete. Run `%s` to reindex documents',
          './bin/search index'));
    } else {
      $console->writeOut(
        "%s\n",
        pht('Nothing to do.'));
    }
  }
}
