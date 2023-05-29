<?php

final class PhabricatorOwnersPackageDatasource
  extends PhabricatorTypeaheadDatasource {

  public function getBrowseTitle() {
    return pht('Browse Packages');
  }

  public function getPlaceholderText() {
    return pht('Type a package name...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorOwnersApplication';
  }

  public function loadResults() {
    $viewer = $this->getViewer();
    $raw_query = $this->getRawQuery();

    $results = array();

    $query = id(new PhabricatorOwnersPackageQuery())
      ->setOrder('name');

    if ($raw_query !== null && strlen($raw_query)) {
      // If the user is querying by monogram explicitly, like "O123", do an ID
      // search. Otherwise, do an ngram substring search.
      if (preg_match('/^[oO]\d+\z/', $raw_query)) {
        $id = trim($raw_query, 'oO');
        $id = (int)$id;
        $query->withIDs(array($id));
      } else {
        $query->withNameNgrams($raw_query);
      }
    }

    $packages = $this->executeQuery($query);
    foreach ($packages as $package) {
      $name = $package->getName();
      $monogram = $package->getMonogram();

      $result = id(new PhabricatorTypeaheadResult())
        ->setName("{$monogram}: {$name}")
        ->setURI($package->getURI())
        ->setPHID($package->getPHID());

      if ($package->isArchived()) {
        $result->setClosed(pht('Archived'));
      }

      $results[] = $result;
    }

    return $this->filterResultsAgainstTokens($results);
  }

}
