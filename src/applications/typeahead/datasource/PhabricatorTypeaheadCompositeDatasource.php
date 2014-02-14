<?php

abstract class PhabricatorTypeaheadCompositeDatasource
  extends PhabricatorTypeaheadDatasource {

  abstract public function getComponentDatasources();

  public function getDatasourceApplicationClass() {
    return null;
  }

  public function loadResults() {
    $results = array();
    foreach ($this->getUsableDatasources() as $source) {
      $source
        ->setRawQuery($this->getRawQuery())
        ->setQuery($this->getQuery())
        ->setViewer($this->getViewer())
        ->setLimit($this->getLimit());

      $results[] = $source->loadResults();
    }
    return array_mergev($results);
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
