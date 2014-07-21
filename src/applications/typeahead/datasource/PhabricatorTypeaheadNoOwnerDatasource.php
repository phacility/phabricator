<?php

final class PhabricatorTypeaheadNoOwnerDatasource
  extends PhabricatorTypeaheadDatasource {

  public function getPlaceholderText() {
    return pht('Type "none"...');
  }

  public function getDatasourceApplicationClass() {
    return null;
  }

  public function loadResults() {
    $viewer = $this->getViewer();
    $raw_query = $this->getRawQuery();

    $results = array();

    $results[] = id(new PhabricatorTypeaheadResult())
      ->setName(pht('None'))
      ->setIcon('fa-ban orange')
      ->setPHID(ManiphestTaskOwner::OWNER_UP_FOR_GRABS);

    return $results;
  }

}
