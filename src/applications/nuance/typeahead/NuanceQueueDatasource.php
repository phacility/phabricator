<?php

final class NuanceQueueDatasource
  extends PhabricatorTypeaheadDatasource {

  public function getBrowseTitle() {
    return pht('Browse Queues');
  }

  public function getPlaceholderText() {
    return pht('Type a queue name...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorNuanceApplication';
  }

  public function loadResults() {
    $viewer = $this->getViewer();
    $raw_query = $this->getRawQuery();

    $results = array();

    // TODO: Make this use real typeahead logic.
    $query = new NuanceQueueQuery();
    $queues = $this->executeQuery($query);

    foreach ($queues as $queue) {
      $results[] = id(new PhabricatorTypeaheadResult())
        ->setName($queue->getName())
        ->setURI('/nuance/queue/'.$queue->getID().'/')
        ->setPHID($queue->getPHID());
    }

    return $this->filterResultsAgainstTokens($results);
  }

}
