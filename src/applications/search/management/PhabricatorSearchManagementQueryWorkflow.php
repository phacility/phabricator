<?php

final class PhabricatorSearchManagementQueryWorkflow
  extends PhabricatorSearchManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('query')
      ->setSynopsis(
        pht('Run a search query. Intended for debugging and development.'))
      ->setArguments(
        array(
          array(
            'name' => 'query',
            'param' => 'query',
            'help' => pht('Raw query to execute.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $viewer = $this->getViewer();
    $raw_query = $args->getArg('query');
    if (!strlen($raw_query)) {
      throw new PhutilArgumentUsageException(
        pht('Specify a query with --query.'));
    }

    $engine = id(new PhabricatorSearchApplicationSearchEngine())
      ->setViewer($viewer);

    $saved = $engine->newSavedQuery();
    $saved->setParameter('query', $raw_query);

    $query = $engine->buildQueryFromSavedQuery($saved);
    $pager = $engine->newPagerForSavedQuery($saved);

    $results = $engine->executeQuery($query, $pager);
    if ($results) {
      foreach ($results as $result) {
        echo tsprintf(
          "%s\t%s\n",
          $result->getPHID(),
          $result->getName());
      }
    } else {
      echo tsprintf(
        "%s\n",
        pht('No results.'));
    }

    return 0;
  }

}
