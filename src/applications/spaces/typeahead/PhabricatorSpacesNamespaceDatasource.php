<?php

final class PhabricatorSpacesNamespaceDatasource
  extends PhabricatorTypeaheadDatasource {

  public function getBrowseTitle() {
    return pht('Browse Spaces');
  }

  public function getPlaceholderText() {
    return pht('Type a space name...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorSpacesApplication';
  }

  public function loadResults() {
    $query = id(new PhabricatorSpacesNamespaceQuery());

    $spaces = $this->executeQuery($query);
    $results = array();
    foreach ($spaces as $space) {
      $results[] = id(new PhabricatorTypeaheadResult())
        ->setName($space->getNamespaceName())
        ->setPHID($space->getPHID());
    }

    return $this->filterResultsAgainstTokens($results);
  }

}
