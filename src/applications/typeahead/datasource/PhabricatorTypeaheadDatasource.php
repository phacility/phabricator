<?php

/**
 * @task functions Token Functions
 */
abstract class PhabricatorTypeaheadDatasource extends Phobject {

  private $viewer;
  private $query;
  private $rawQuery;
  private $offset;
  private $limit;
  private $parameters = array();

  public function setLimit($limit) {
    $this->limit = $limit;
    return $this;
  }

  public function getLimit() {
    return $this->limit;
  }

  public function setOffset($offset) {
    $this->offset = $offset;
    return $this;
  }

  public function getOffset() {
    return $this->offset;
  }

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function setRawQuery($raw_query) {
    $this->rawQuery = $raw_query;
    return $this;
  }

  public function getRawQuery() {
    return $this->rawQuery;
  }

  public function setQuery($query) {
    $this->query = $query;
    return $this;
  }

  public function getQuery() {
    return $this->query;
  }

  public function setParameters(array $params) {
    $this->parameters = $params;
    return $this;
  }

  public function getParameters() {
    return $this->parameters;
  }

  public function getParameter($name, $default = null) {
    return idx($this->parameters, $name, $default);
  }

  public function getDatasourceURI() {
    $uri = new PhutilURI('/typeahead/class/'.get_class($this).'/');
    $uri->setQueryParams($this->parameters);
    return (string)$uri;
  }

  public function getBrowseURI() {
    if (!$this->isBrowsable()) {
      return null;
    }

    $uri = new PhutilURI('/typeahead/browse/'.get_class($this).'/');
    $uri->setQueryParams($this->parameters);
    return (string)$uri;
  }

  abstract public function getPlaceholderText();
  abstract public function getDatasourceApplicationClass();
  abstract public function loadResults();

  public static function tokenizeString($string) {
    $string = phutil_utf8_strtolower($string);
    $string = trim($string);
    if (!strlen($string)) {
      return array();
    }

    $tokens = preg_split('/\s+|[-\[\]]/', $string);
    return array_unique($tokens);
  }

  public function getTokens() {
    return self::tokenizeString($this->getRawQuery());
  }

  protected function executeQuery(
    PhabricatorCursorPagedPolicyAwareQuery $query) {

    return $query
      ->setViewer($this->getViewer())
      ->setOffset($this->getOffset())
      ->setLimit($this->getLimit())
      ->execute();
  }


  /**
   * Can the user browse through results from this datasource?
   *
   * Browsable datasources allow the user to switch from typeahead mode to
   * a browse mode where they can scroll through all results.
   *
   * By default, datasources are browsable, but some datasources can not
   * generate a meaningful result set or can't filter results on the server.
   *
   * @return bool
   */
  public function isBrowsable() {
    return true;
  }


  /**
   * Filter a list of results, removing items which don't match the query
   * tokens.
   *
   * This is useful for datasources which return a static list of hard-coded
   * or configured results and can't easily do query filtering in a real
   * query class. Instead, they can just build the entire result set and use
   * this method to filter it.
   *
   * For datasources backed by database objects, this is often much less
   * efficient than filtering at the query level.
   *
   * @param list<PhabricatorTypeaheadResult> List of typeahead results.
   * @return list<PhabricatorTypeaheadResult> Filtered results.
   */
  protected function filterResultsAgainstTokens(array $results) {
    $tokens = $this->getTokens();
    if (!$tokens) {
      return $results;
    }

    $map = array();
    foreach ($tokens as $token) {
      $map[$token] = strlen($token);
    }

    foreach ($results as $key => $result) {
      $rtokens = self::tokenizeString($result->getName());

      // For each token in the query, we need to find a match somewhere
      // in the result name.
      foreach ($map as $token => $length) {
        // Look for a match.
        $match = false;
        foreach ($rtokens as $rtoken) {
          if (!strncmp($rtoken, $token, $length)) {
            // This part of the result name has the query token as a prefix.
            $match = true;
            break;
          }
        }

        if (!$match) {
          // We didn't find a match for this query token, so throw the result
          // away. Try with the next result.
          unset($results[$key]);
          break;
        }
      }
    }

    return $results;
  }

  protected function newFunctionResult() {
    return id(new PhabricatorTypeaheadResult())
      ->setTokenType(PhabricatorTypeaheadTokenView::TYPE_FUNCTION)
      ->setIcon('fa-asterisk');
  }

  public function newInvalidToken($name) {
    return id(new PhabricatorTypeaheadTokenView())
      ->setValue($name)
      ->setIcon('fa-exclamation-circle')
      ->setTokenType(PhabricatorTypeaheadTokenView::TYPE_INVALID);
  }

/* -(  Token Functions  )---------------------------------------------------- */


  /**
   * @task functions
   */
  protected function canEvaluateFunction($function) {
    return false;
  }


  /**
   * @task functions
   */
  protected function evaluateFunction($function, array $argv_list) {
    throw new PhutilMethodNotImplementedException();
  }


  /**
   * @task functions
   */
  public function evaluateTokens(array $tokens) {
    $results = array();
    $evaluate = array();
    foreach ($tokens as $token) {
      if (!self::isFunctionToken($token)) {
        $results[] = $token;
      } else {
        $evaluate[] = $token;
      }
    }

    foreach ($evaluate as $function) {
      $function = self::parseFunction($function);
      if (!$function) {
        throw new PhabricatorTypeaheadInvalidTokenException();
      }

      $name = $function['name'];
      $argv = $function['argv'];

      foreach ($this->evaluateFunction($name, array($argv)) as $phid) {
        $results[] = $phid;
      }
    }

    return $results;
  }


  /**
   * @task functions
   */
  public static function isFunctionToken($token) {
    // We're looking for a "(" so that a string like "members(q" is identified
    // and parsed as a function call. This allows us to start generating
    // results immeidately, before the user fully types out "members(quack)".
    return (strpos($token, '(') !== false);
  }


  /**
   * @task functions
   */
  public function parseFunction($token, $allow_partial = false) {
    $matches = null;

    if ($allow_partial) {
      $ok = preg_match('/^([^(]+)\((.*)$/', $token, $matches);
    } else {
      $ok = preg_match('/^([^(]+)\((.*)\)$/', $token, $matches);
    }

    if (!$ok) {
      return null;
    }

    $function = trim($matches[1]);

    if (!$this->canEvaluateFunction($function)) {
      return null;
    }

    return array(
      'name' => $function,
      'argv' => array(trim($matches[2])),
    );
  }


  /**
   * @task functions
   */
  public function renderFunctionTokens($function, array $argv_list) {
    throw new PhutilMethodNotImplementedException();
  }


}
