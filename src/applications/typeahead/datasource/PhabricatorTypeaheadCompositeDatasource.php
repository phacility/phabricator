<?php

abstract class PhabricatorTypeaheadCompositeDatasource
  extends PhabricatorTypeaheadDatasource {

  private $usable;

  abstract public function getComponentDatasources();

  public function isBrowsable() {
    foreach ($this->getUsableDatasources() as $datasource) {
      if (!$datasource->isBrowsable()) {
        return false;
      }
    }

    return parent::isBrowsable();
  }

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
    if ($this->usable === null) {
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

        $source->setViewer($this->getViewer());
        $usable[] = $source;
      }
      $this->usable = $usable;
    }

    return $this->usable;
  }


  protected function canEvaluateFunction($function) {
    foreach ($this->getUsableDatasources() as $source) {
      if ($source->canEvaluateFunction($function)) {
        return true;
      }
    }

    return parent::canEvaluateFunction($function);
  }


  protected function evaluateFunction($function, array $argv) {
    foreach ($this->getUsableDatasources() as $source) {
      if ($source->canEvaluateFunction($function)) {
        return $source->evaluateFunction($function, $argv);
      }
    }

    return parent::evaluateFunction($function, $argv);
  }

  public function renderFunctionTokens($function, array $argv_list) {
    foreach ($this->getUsableDatasources() as $source) {
      if ($source->canEvaluateFunction($function)) {
        return $source->renderFunctionTokens($function, $argv_list);
      }
    }

    return parent::renderFunctionTokens($function, $argv_list);
  }


}
