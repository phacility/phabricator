<?php

final class LiskRawMigrationIterator extends PhutilBufferedIterator {

  private $conn;
  private $table;
  private $cursor;
  private $column = 'id';

  public function __construct(AphrontDatabaseConnection $conn, $table) {
    $this->conn = $conn;
    $this->table = $table;
  }

  protected function didRewind() {
    $this->cursor = 0;
  }

  public function key() {
    return idx($this->current(), $this->column);
  }

  protected function loadPage() {
    $page = queryfx_all(
      $this->conn,
      'SELECT * FROM %T WHERE %C > %d ORDER BY ID ASC LIMIT %d',
      $this->table,
      $this->column,
      $this->cursor,
      $this->getPageSize());

    if ($page) {
      $this->cursor = idx(last($page), $this->column);
    }

    return $page;
  }

}
