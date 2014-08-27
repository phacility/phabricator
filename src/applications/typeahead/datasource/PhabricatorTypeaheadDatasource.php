<?php

abstract class PhabricatorTypeaheadDatasource extends Phobject {

  private $viewer;
  private $query;
  private $rawQuery;
  private $limit;
  private $parameters = array();

  public function setLimit($limit) {
    $this->limit = $limit;
    return $this;
  }

  public function getLimit() {
    return $this->limit;
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

  abstract public function getPlaceholderText();
  abstract public function getDatasourceApplicationClass();
  abstract public function loadResults();

  public static function tokenizeString($string) {
    $string = phutil_utf8_strtolower($string);
    $string = trim($string);
    if (!strlen($string)) {
      return array();
    }

    $tokens = preg_split('/\s+|-/', $string);
    return array_unique($tokens);
  }

}
