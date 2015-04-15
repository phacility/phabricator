<?php

abstract class PhabricatorTypeaheadCompositeDatasource
  extends PhabricatorTypeaheadDatasource {

  abstract public function getComponentDatasources();

  public function getDatasourceApplicationClass() {
    return null;
  }

  public function loadResults() {
    $offset = $this->getOffset();
    $limit = $this->getLimit();

    $results = array();
    foreach ($this->getUsableDatasources() as $source) {
      $source
        ->setRawQuery($this->getRawQuery())
        ->setQuery($this->getQuery())
        ->setViewer($this->getViewer());

      if ($limit) {
        $source->setLimit($offset + $limit);
      }

      $results[] = $source->loadResults();
    }

    $results = array_mergev($results);
    $results = msort($results, 'getSortKey');

    $count = count($results);
    if ($offset || $limit) {
      if (!$limit) {
        $limit = count($results);
      }

      $results = array_slice($results, $offset, $limit, $preserve_keys = true);
    }

    return $results;
  }

  private function getUsableDatasources() {
    $sources = $this->getComponentDatasources();

    $usable = array();
    foreach ($sources as $source) {
      $application_class = $source->getDatasourceApplicationClass();

      if ($application_class) {
        $result = id(new PhabricatorApplicationQuery())
          ->setViewer($this->getViewer())
          ->withClasses(array($application_class))
          ->execute();
        if (!$result) {
          continue;
        }
      }

      $usable[] = $source;
    }

    return $usable;
  }

}
