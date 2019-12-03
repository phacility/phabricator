<?php

final class AphrontDatabaseTableRef
  extends Phobject
  implements AphrontDatabaseTableRefInterface {

  private $database;
  private $table;

  public function __construct($database, $table) {
    $this->database = $database;
    $this->table = $table;
  }

  public function getAphrontRefDatabaseName() {
    return $this->database;
  }

  public function getAphrontRefTableName() {
    return $this->table;
  }

}
