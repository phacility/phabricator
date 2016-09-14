<?php

final class PhabricatorPackagesPublisherDatasource
  extends PhabricatorTypeaheadDatasource {

  public function getBrowseTitle() {
    return pht('Browse Package Publishers');
  }

  public function getPlaceholderText() {
    return pht('Type a publisher name...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorPackagesApplication';
  }

  public function loadResults() {
    $viewer = $this->getViewer();
    $raw_query = $this->getRawQuery();

    $publisher_query = id(new PhabricatorPackagesPublisherQuery());
    $publishers = $this->executeQuery($publisher_query);

    $results = array();
    foreach ($publishers as $publisher) {
      $results[] = id(new PhabricatorTypeaheadResult())
        ->setName($publisher->getName())
        ->setPHID($publisher->getPHID());
    }

    return $this->filterResultsAgainstTokens($results);
  }

}
