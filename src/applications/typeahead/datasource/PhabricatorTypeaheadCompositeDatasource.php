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

    // If the input query is a function like `members(platy`, and we can
    // parse the function, we strip the function off and hand the stripped
    // query to child sources. This makes it easier to implement function
    // sources in terms of real object sources.
    $raw_query = $this->getRawQuery();

    $is_function = false;
    if (self::isFunctionToken($raw_query)) {
      $is_function = true;
    }

    $stack = $this->getFunctionStack();

    $results = array();
    foreach ($this->getUsableDatasources() as $source) {
      $source_stack = $stack;

      $source_query = $raw_query;
      if ($is_function) {
        // If this source can't handle the function, skip it.
        $function = $source->parseFunction($raw_query, $allow_partial = true);
        if (!$function) {
          continue;
        }

        // If this source handles the function directly, strip the function.
        // Otherwise, this is something like a composite source which has
        // some internal source which can evaluate the function, but will
        // perform stripping later.
        if ($source->shouldStripFunction($function['name'])) {
          $source_query = head($function['argv']);
          $source_stack[] = $function['name'];
        }
      }

      $source
        ->setFunctionStack($source_stack)
        ->setRawQuery($source_query)
        ->setQuery($this->getQuery())
        ->setViewer($this->getViewer());

      if ($limit) {
        $source->setLimit($offset + $limit);
      }

      $source_results = $source->loadResults();
      $source_results = $source->didLoadResults($source_results);
      $results[] = $source_results;
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

  public function getAllDatasourceFunctions() {
    $results = parent::getAllDatasourceFunctions();
    foreach ($this->getUsableDatasources() as $source) {
      $results += $source->getAllDatasourceFunctions();
    }
    return $results;
  }

  protected function didEvaluateTokens(array $results) {
    foreach ($this->getUsableDatasources() as $source) {
      $results = $source->didEvaluateTokens($results);
    }
    return $results;
  }

  protected function canEvaluateFunction($function) {
    foreach ($this->getUsableDatasources() as $source) {
      if ($source->canEvaluateFunction($function)) {
        return true;
      }
    }

    return parent::canEvaluateFunction($function);
  }

  protected function evaluateValues(array $values) {
    foreach ($this->getUsableDatasources() as $source) {
      $values = $source->evaluateValues($values);
    }

    return parent::evaluateValues($values);
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

  protected function renderSpecialTokens(array $values) {
    $result = array();
    foreach ($this->getUsableDatasources() as $source) {
      $special = $source->renderSpecialTokens($values);
      foreach ($special as $key => $token) {
        $result[$key] = $token;
        unset($values[$key]);
      }
      if (!$values) {
        break;
      }
    }
    return $result;
  }


}
