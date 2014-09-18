<?php

final class PhabricatorConfigSchemaQuery extends Phobject {

  private $api;

  public function setAPI(PhabricatorStorageManagementAPI $api) {
    $this->api = $api;
    return $this;
  }

  protected function getAPI() {
    if (!$this->api) {
      throw new Exception(pht('Call setAPI() before issuing a query!'));
    }
    return $this->api;
  }

  protected function getConn() {
    return $this->getAPI()->getConn(null);
  }

  private function getDatabaseNames() {
    $api = $this->getAPI();
    $patches = PhabricatorSQLPatchList::buildAllPatches();
    return $api->getDatabaseList(
      $patches,
      $only_living = true);
  }

  public function loadActualSchema() {
    $databases = $this->getDatabaseNames();

    $conn = $this->getConn();
    $tables = queryfx_all(
      $conn,
      'SELECT TABLE_SCHEMA, TABLE_NAME, TABLE_COLLATION
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA IN (%Ls)',
      $databases);

    $database_info = queryfx_all(
      $conn,
      'SELECT SCHEMA_NAME, DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME
        FROM INFORMATION_SCHEMA.SCHEMATA
        WHERE SCHEMA_NAME IN (%Ls)',
      $databases);
    $database_info = ipull($database_info, null, 'SCHEMA_NAME');

    $server_schema = new PhabricatorConfigServerSchema();

    $tables = igroup($tables, 'TABLE_SCHEMA');
    foreach ($tables as $database_name => $database_tables) {
      $info = $database_info[$database_name];

      $database_schema = id(new PhabricatorConfigDatabaseSchema())
        ->setName($database_name)
        ->setCharacterSet($info['DEFAULT_CHARACTER_SET_NAME'])
        ->setCollation($info['DEFAULT_COLLATION_NAME']);

      foreach ($database_tables as $table) {
        $table_name = $table['TABLE_NAME'];

        $table_schema = id(new PhabricatorConfigTableSchema())
          ->setName($table_name)
          ->setCollation($table['TABLE_COLLATION']);

        $columns = queryfx_all(
          $conn,
          'SELECT COLUMN_NAME, CHARACTER_SET_NAME, COLLATION_NAME, COLUMN_TYPE
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s',
          $database_name,
          $table_name);

        foreach ($columns as $column) {
          $column_schema = id(new PhabricatorConfigColumnSchema())
            ->setName($column['COLUMN_NAME'])
            ->setCharacterSet($column['CHARACTER_SET_NAME'])
            ->setCollation($column['COLLATION_NAME'])
            ->setColumnType($column['COLUMN_TYPE']);

          $table_schema->addColumn($column_schema);
        }

        $database_schema->addTable($table_schema);
      }

      $server_schema->addDatabase($database_schema);
    }

    return $server_schema;
  }


  public function loadExpectedSchema() {
    $databases = $this->getDatabaseNames();

    $api = $this->getAPI();

    if ($api->isCharacterSetAvailable('utf8mb4')) {
      // If utf8mb4 is available, we use it with the utf8mb4_unicode_ci
      // collation. This is most correct, and will sort properly.

      $utf8_charset = 'utf8mb4';
      $utf8_collate = 'utf8mb4_unicode_ci';
    } else {
      // If utf8mb4 is not available, we use binary. This allows us to store
      // 4-byte unicode characters. This has some tradeoffs:
      //
      // Unicode characters won't sort correctly. There's nothing we can do
      // about this while still supporting 4-byte characters.
      //
      // It's possible that strings will be truncated in the middle of a
      // character on insert. We encourage users to set STRICT_ALL_TABLES
      // to prevent this.

      $utf8_charset = 'binary';
      $utf8_collate = 'binary';
    }

    $server_schema = new PhabricatorConfigServerSchema();
    foreach ($databases as $database_name) {
      $database_schema = id(new PhabricatorConfigDatabaseSchema())
        ->setName($database_name)
        ->setCharacterSet($utf8_charset)
        ->setCollation($utf8_collate);

      $server_schema->addDatabase($database_schema);
    }

    return $server_schema;
  }

}
