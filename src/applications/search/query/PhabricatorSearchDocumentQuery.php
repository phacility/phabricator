<?php

final class PhabricatorSearchDocumentQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $savedQuery;

  public function withSavedQuery(PhabricatorSavedQuery $query) {
    $this->savedQuery = $query;
    return $this;
  }

  protected function loadPage() {
    $phids = $this->loadDocumentPHIDsWithoutPolicyChecks();

    $handles = id(new PhabricatorHandleQuery())
      ->setViewer($this->getViewer())
      ->withPHIDs($phids)
      ->execute();

    // Retain engine order.
    $handles = array_select_keys($handles, $phids);

    return $handles;
  }

  protected function willFilterPage(array $handles) {
    foreach ($handles as $key => $handle) {
      if (!$handle->isComplete()) {
        unset($handles[$key]);
        continue;
      }
      if ($handle->getPolicyFiltered()) {
        unset($handles[$key]);
        continue;
      }
    }

    return $handles;
  }

  public function loadDocumentPHIDsWithoutPolicyChecks() {
    $query = id(clone($this->savedQuery))
      ->setParameter('offset', $this->getOffset())
      ->setParameter('limit', $this->getRawResultLimit());

    $engine = PhabricatorSearchEngineSelector::newSelector()->newEngine();

    return $engine->executeSearch($query);
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorApplicationSearch';
  }

  protected function getPagingValue($result) {
    throw new Exception(
      pht(
        'This query does not support cursor paging; it must be offset '.
        'paged.'));
  }

  protected function nextPage(array $page) {
    $this->setOffset($this->getOffset() + count($page));
    return $this;
  }

}
