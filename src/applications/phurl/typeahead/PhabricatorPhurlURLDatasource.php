<?php

final class PhabricatorPhurlURLDatasource
  extends PhabricatorTypeaheadDatasource {

  public function getBrowseTitle() {
    return pht('Browse Phurl URLs');
  }

  public function getPlaceholderText() {
    return pht('Select a phurl...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorPhurlApplication';
  }

  public function loadResults() {
    $query = id(new PhabricatorPhurlURLQuery());
    $urls = $this->executeQuery($query);
    $results = array();
    foreach ($urls as $url) {
      $result = id(new PhabricatorTypeaheadResult())
        ->setDisplayName($url->getName())
        ->setName($url->getName()." ".$url->getAlias())
        ->setPHID($url->getPHID())
        ->addAttribute($url->getLongURL());

      $results[] = $result;
    }

    return $this->filterResultsAgainstTokens($results);
  }

}
