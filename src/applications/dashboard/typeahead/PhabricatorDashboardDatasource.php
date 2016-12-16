<?php

final class PhabricatorDashboardDatasource
  extends PhabricatorTypeaheadDatasource {

  public function getBrowseTitle() {
    return pht('Browse Dashboards');
  }

  public function getPlaceholderText() {
    return pht('Type a dashboard name...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorDashboardApplication';
  }

  public function loadResults() {
    $query = id(new PhabricatorDashboardQuery());

    $dashboards = $this->executeQuery($query);
    $results = array();
    foreach ($dashboards as $dashboard) {
      $result = id(new PhabricatorTypeaheadResult())
        ->setName($dashboard->getName())
        ->setPHID($dashboard->getPHID())
        ->addAttribute(pht('Dashboard'));

      if ($dashboard->isArchived()) {
        $result->setClosed(pht('Archived'));
      }

      $results[] = $result;
    }

    return $this->filterResultsAgainstTokens($results);
  }

}
