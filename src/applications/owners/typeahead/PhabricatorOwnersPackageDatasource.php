<?php

final class PhabricatorOwnersPackageDatasource
  extends PhabricatorTypeaheadDatasource {

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

    $packages = id(new PhabricatorOwnersPackageQuery())
      ->setViewer($viewer)
      ->execute();

    foreach ($packages as $package) {
      $results[] = id(new PhabricatorTypeaheadResult())
        ->setName($package->getName())
        ->setURI('/owners/package/'.$package->getID().'/')
        ->setPHID($package->getPHID());
    }

    return $results;
  }

}
