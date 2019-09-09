<?php

final class AphrontParameterQueryException extends AphrontQueryException {

  private $query;

  public function __construct($query, $message) {
    parent::__construct(pht('%s Query: %s', $message, $query));
    $this->query = $query;
  }

  public function getQuery() {
    return $this->query;
  }

}
