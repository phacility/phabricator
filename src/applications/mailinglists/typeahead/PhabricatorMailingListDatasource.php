<?php

final class PhabricatorMailingListDatasource
  extends PhabricatorTypeaheadDatasource {

  public function isBrowsable() {
    // TODO: Make this browsable if we don't delete it before then.
    return false;
  }

  public function getPlaceholderText() {
    return pht('Type a mailing list name...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorMailingListsApplication';
  }

  public function loadResults() {
    $viewer = $this->getViewer();
    $raw_query = $this->getRawQuery();

    $results = array();

    $lists = id(new PhabricatorMailingListQuery())
      ->setViewer($viewer)
      ->execute();
    foreach ($lists as $list) {
      $results[] = id(new PhabricatorTypeaheadResult())
        ->setName($list->getName())
        ->setURI($list->getURI())
        ->setPHID($list->getPHID());
    }

    return $results;
  }

}
