<?php

/**
 * Configurable test query for implementing Policy unit tests.
 */
final class PhabricatorPolicyAwareTestQuery
  extends PhabricatorPolicyAwareQuery {

  private $results;
  private $offset = 0;

  public function setResults(array $results) {
    $this->results = $results;
    return $this;
  }

  protected function willExecute() {
    $this->offset = 0;
  }

  protected function loadPage() {
    if ($this->getRawResultLimit()) {
      return array_slice(
        $this->results,
        $this->offset,
        $this->getRawResultLimit());
    } else {
      return array_slice($this->results, $this->offset);
    }
  }

  protected function nextPage(array $page) {
    $this->offset += count($page);
  }

  public function getQueryApplicationClass() {
    return null;
  }

}
