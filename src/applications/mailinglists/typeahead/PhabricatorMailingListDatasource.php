<?php

final class PhabricatorMailingListDatasource
  extends PhabricatorTypeaheadDatasource {

  public function getBrowseTitle() {
    return pht('Browse Mailing Lists');
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

    $query = id(new PhabricatorMailingListQuery());
    $lists = $this->executeQuery($query);

    $results = array();
    foreach ($lists as $list) {
      $results[] = id(new PhabricatorTypeaheadResult())
        ->setName($list->getName())
        ->setURI($list->getURI())
        ->setPHID($list->getPHID());
    }

    // TODO: It would be slightly preferable to do this as part of the query,
    // this is just simpler for the moment.

    return $this->filterResultsAgainstTokens($results);
  }

}
