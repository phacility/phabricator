<?php

final class PhabricatorDashboardPortalDatasource
  extends PhabricatorTypeaheadDatasource {

  public function getBrowseTitle() {
    return pht('Browse Portals');
  }

  public function getPlaceholderText() {
    return pht('Type a portal name...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorDashboardApplication';
  }

  public function loadResults() {
    $results = $this->buildResults();
    return $this->filterResultsAgainstTokens($results);
  }

  protected function renderSpecialTokens(array $values) {
    return $this->renderTokensFromResults($this->buildResults(), $values);
  }

  public function buildResults() {
    $query = new PhabricatorDashboardPortalQuery();

    // TODO: Actually query by name so this scales past 100 portals.

    $portals = $this->executeQuery($query);

    $results = array();
    foreach ($portals as $portal) {
      $result = id(new PhabricatorTypeaheadResult())
        ->setName($portal->getObjectName().' '.$portal->getName())
        ->setPHID($portal->getPHID())
        ->setIcon('fa-compass');

      $results[] = $result;
    }

    return $results;
  }

}
