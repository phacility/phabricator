<?php

final class PhabricatorMetaMTAApplicationEmailDatasource
  extends PhabricatorTypeaheadDatasource {

  public function isBrowsable() {
    // TODO: Make this browsable.
    return false;
  }

  public function getBrowseTitle() {
    return pht('Browse Email Addresses');
  }

  public function getPlaceholderText() {
    return pht('Type an application email address...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorMetaMTAApplication';
  }

  public function loadResults() {
    $viewer = $this->getViewer();
    $raw_query = $this->getRawQuery();

    $emails = id(new PhabricatorMetaMTAApplicationEmailQuery())
      ->setViewer($viewer)
      ->withAddressPrefix($raw_query)
      ->setLimit($this->getLimit())
      ->execute();

    if ($emails) {
      $handles = id(new PhabricatorHandleQuery())
        ->setViewer($viewer)
        ->withPHIDs(mpull($emails, 'getPHID'))
        ->execute();
    } else {
      $handles = array();
    }

    $results = array();
    foreach ($handles as $handle) {
      $results[] = id(new PhabricatorTypeaheadResult())
        ->setName($handle->getName())
        ->setPHID($handle->getPHID());
    }

    return $results;
  }

}
