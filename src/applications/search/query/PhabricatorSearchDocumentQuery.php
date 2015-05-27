<?php

final class PhabricatorSearchDocumentQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $savedQuery;
  private $objectCapabilities;

  public function withSavedQuery(PhabricatorSavedQuery $query) {
    $this->savedQuery = $query;
    return $this;
  }

  public function requireObjectCapabilities(array $capabilities) {
    $this->objectCapabilities = $capabilities;
    return $this;
  }

  protected function getRequiredObjectCapabilities() {
    if ($this->objectCapabilities) {
      return $this->objectCapabilities;
    }
    return $this->getRequiredCapabilities();
  }

  protected function loadPage() {
    $phids = $this->loadDocumentPHIDsWithoutPolicyChecks();

    $handles = id(new PhabricatorHandleQuery())
      ->setViewer($this->getViewer())
      ->requireObjectCapabilities($this->getRequiredObjectCapabilities())
      ->withPHIDs($phids)
      ->execute();

    // Retain engine order.
    $handles = array_select_keys($handles, $phids);

    return $handles;
  }

  protected function willFilterPage(array $handles) {
    // NOTE: This is used by the object selector dialog to exclude the object
    // you're looking at, so that, e.g., a task can't be set as a dependency
    // of itself in the UI.

    // TODO: Remove this after object selection moves to ApplicationSearch.

    $exclude = array();
    if ($this->savedQuery) {
      $exclude_phids = $this->savedQuery->getParameter('excludePHIDs', array());
      $exclude = array_fuse($exclude_phids);
    }

    foreach ($handles as $key => $handle) {
      if (!$handle->isComplete()) {
        unset($handles[$key]);
        continue;
      }
      if ($handle->getPolicyFiltered()) {
        unset($handles[$key]);
        continue;
      }
      if (isset($exclude[$handle->getPHID()])) {
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

    $engine = PhabricatorSearchEngine::loadEngine();

    return $engine->executeSearch($query);
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorSearchApplication';
  }

  protected function getResultCursor($result) {
    throw new Exception(
      pht(
        'This query does not support cursor paging; it must be offset paged.'));
  }

  protected function nextPage(array $page) {
    $this->setOffset($this->getOffset() + count($page));
    return $this;
  }

}
