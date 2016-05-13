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
      ->withNameNgrams($raw_query)
      ->setOrder('name');

    $packages = $this->executeQuery($query);
    foreach ($packages as $package) {
      $name = $package->getName();
      $monogram = $package->getMonogram();

      $results[] = id(new PhabricatorTypeaheadResult())
        ->setName("{$monogram}: {$name}")
        ->setURI($package->getURI())
        ->setPHID($package->getPHID());
    }

    return $this->filterResultsAgainstTokens($results);
  }

}
