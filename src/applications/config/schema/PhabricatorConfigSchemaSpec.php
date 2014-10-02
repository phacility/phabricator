<?php

abstract class PhabricatorConfigSchemaSpec extends Phobject {

  private $server;
  private $utf8Charset;
  private $utf8BinaryCollation;
  private $utf8SortingCollation;

  public function setUTF8SortingCollation($utf8_sorting_collation) {
    $this->utf8SortingCollation = $utf8_sorting_collation;
    return $this;
  }

  public function getUTF8SortingCollation() {
    return $this->utf8SortingCollation;
  }

  public function setUTF8BinaryCollation($utf8_binary_collation) {
    $this->utf8BinaryCollation = $utf8_binary_collation;
    return $this;
  }

  public function getUTF8BinaryCollation() {
    return $this->utf8BinaryCollation;
  }

  public function setUTF8Charset($utf8_charset) {
    $this->utf8Charset = $utf8_charset;
    return $this;
  }

  public function getUTF8Charset() {
    return $this->utf8Charset;
  }

  public function setServer(PhabricatorConfigServerSchema $server) {
    $this->server = $server;
    return $this;
  }

  public function getServer() {
    return $this->server;
  }

  abstract public function buildSchemata();

  protected function buildLiskObjectSchema(PhabricatorLiskDAO $object) {
    $this->buildRawSchema(
      $object->getApplicationName(),
      $object->getTableName(),
      $object->getSchemaColumns(),
      $object->getSchemaKeys());
  }

  protected function buildRawSchema(
    $database_name,
    $table_name,
    array $columns,
    array $keys) {
    $database = $this->getDatabase($database_name);

    $table = $this->newTable($table_name);

    foreach ($columns as $name => $type) {
      if ($type === null) {
        continue;
      }

      $details = $this->getDetailsForDataType($type);
      list($column_type, $charset, $collation, $nullable, $auto) = $details;

      $column = $this->newColumn($name)
        ->setDataType($type)
        ->setColumnType($column_type)
        ->setCharacterSet($charset)
        ->setCollation($collation)
        ->setNullable($nullable)
        ->setAutoIncrement($auto);

      $table->addColumn($column);
    }

    foreach ($keys as $key_name => $key_spec) {
      if ($key_spec === null) {
        // This is a subclass removing a key which Lisk expects.
        continue;
      }

      $key = $this->newKey($key_name)
        ->setColumnNames(idx($key_spec, 'columns', array()));

      $key->setUnique((bool)idx($key_spec, 'unique'));
      $key->setIndexType(idx($key_spec, 'type', 'BTREE'));

      $table->addKey($key);
    }

    $database->addTable($table);
  }

  protected function buildEdgeSchemata(PhabricatorLiskDAO $object) {
    $this->buildRawSchema(
      $object->getApplicationName(),
      PhabricatorEdgeConfig::TABLE_NAME_EDGE,
      array(
        'src' => 'phid',
        'type' => 'uint32',
        'dst' => 'phid',
        'dateCreated' => 'epoch',
        'seq' => 'uint32',
        'dataID' => 'id?',
      ),
      array(
        'PRIMARY' => array(
          'columns' => array('src', 'type', 'dst'),
          'unique' => true,
        ),
        'src' => array(
          'columns' => array('src', 'type', 'dateCreated', 'seq'),
        ),
        'key_dst' => array(
          'columns' => array('dst', 'type', 'src'),
          'unique' => true,
        ),
      ));

    $this->buildRawSchema(
      $object->getApplicationName(),
      PhabricatorEdgeConfig::TABLE_NAME_EDGEDATA,
      array(
        'id' => 'auto',
        'data' => 'text',
      ),
      array(
        'PRIMARY' => array(
          'columns' => array('id'),
          'unique' => true,
        ),
      ));
  }

  protected function getDatabase($name) {
    $server = $this->getServer();

    $database = $server->getDatabase($this->getNamespacedDatabase($name));
    if (!$database) {
      $database = $this->newDatabase($name);
      $server->addDatabase($database);
    }

    return $database;
  }

  protected function newDatabase($name) {
    return id(new PhabricatorConfigDatabaseSchema())
      ->setName($this->getNamespacedDatabase($name))
      ->setCharacterSet($this->getUTF8Charset())
      ->setCollation($this->getUTF8BinaryCollation());
  }

  protected function getNamespacedDatabase($name) {
    $namespace = PhabricatorLiskDAO::getStorageNamespace();
    return $namespace.'_'.$name;
  }

  protected function newTable($name) {
    return id(new PhabricatorConfigTableSchema())
      ->setName($name)
      ->setCollation($this->getUTF8BinaryCollation());
  }

  protected function newColumn($name) {
    return id(new PhabricatorConfigColumnSchema())
      ->setName($name);
  }

  protected function newKey($name) {
    return id(new PhabricatorConfigKeySchema())
      ->setName($name);
  }

  private function getDetailsForDataType($data_type) {
    $column_type = null;
    $charset = null;
    $collation = null;
    $auto = false;

    // If the type ends with "?", make the column nullable.
    $nullable = false;
    if (preg_match('/\?$/', $data_type)) {
      $nullable = true;
      $data_type = substr($data_type, 0, -1);
    }

    // NOTE: MySQL allows fragments like "VARCHAR(32) CHARACTER SET binary",
    // but just interprets that to mean "VARBINARY(32)". The fragment is
    // totally disallowed in a MODIFY statement vs a CREATE TABLE statement.

    switch ($data_type) {
      case 'auto':
        $column_type = 'int(10) unsigned';
        $auto = true;
        break;
      case 'auto64':
        $column_type = 'bigint(20) unsigned';
        $auto = true;
        break;
      case 'id':
      case 'epoch':
      case 'uint32':
        $column_type = 'int(10) unsigned';
        break;
      case 'sint32':
        $column_type = 'int(10)';
        break;
      case 'id64':
      case 'uint64':
        $column_type = 'bigint(20) unsigned';
        break;
      case 'sint64':
        $column_type = 'bigint(20)';
        break;
      case 'phid':
      case 'policy';
        $column_type = 'varbinary(64)';
        break;
      case 'bytes64':
        $column_type = 'binary(64)';
        break;
      case 'bytes40':
        $column_type = 'binary(40)';
        break;
      case 'bytes32':
        $column_type = 'binary(32)';
        break;
      case 'bytes20':
        $column_type = 'binary(20)';
        break;
      case 'bytes12':
        $column_type = 'binary(12)';
        break;
      case 'bytes4':
        $column_type = 'binary(4)';
        break;
      case 'bytes':
        $column_type = 'longblob';
        break;
      case 'sort255':
        $column_type = 'varchar(255)';
        $charset = $this->getUTF8Charset();
        $collation = $this->getUTF8SortingCollation();
        break;
      case 'sort128':
        $column_type = 'varchar(128)';
        $charset = $this->getUTF8Charset();
        $collation = $this->getUTF8SortingCollation();
        break;
      case 'sort64':
        $column_type = 'varchar(64)';
        $charset = $this->getUTF8Charset();
        $collation = $this->getUTF8SortingCollation();
        break;
      case 'sort32':
        $column_type = 'varchar(32)';
        $charset = $this->getUTF8Charset();
        $collation = $this->getUTF8SortingCollation();
        break;
      case 'sort':
        $column_type = 'longtext';
        $charset = $this->getUTF8Charset();
        $collation = $this->getUTF8SortingCollation();
        break;
      case 'text255':
        $column_type = 'varchar(255)';
        $charset = $this->getUTF8Charset();
        $collation = $this->getUTF8BinaryCollation();
        break;
      case 'text160':
        $column_type = 'varchar(160)';
        $charset = $this->getUTF8Charset();
        $collation = $this->getUTF8BinaryCollation();
        break;
      case 'text128':
        $column_type = 'varchar(128)';
        $charset = $this->getUTF8Charset();
        $collation = $this->getUTF8BinaryCollation();
        break;
      case 'text80':
        $column_type = 'varchar(80)';
        $charset = $this->getUTF8Charset();
        $collation = $this->getUTF8BinaryCollation();
        break;
      case 'text64':
        $column_type = 'varchar(64)';
        $charset = $this->getUTF8Charset();
        $collation = $this->getUTF8BinaryCollation();
        break;
      case 'text40':
        $column_type = 'varchar(40)';
        $charset = $this->getUTF8Charset();
        $collation = $this->getUTF8BinaryCollation();
        break;
      case 'text32':
        $column_type = 'varchar(32)';
        $charset = $this->getUTF8Charset();
        $collation = $this->getUTF8BinaryCollation();
        break;
      case 'text20':
        $column_type = 'varchar(20)';
        $charset = $this->getUTF8Charset();
        $collation = $this->getUTF8BinaryCollation();
        break;
      case 'text16':
        $column_type = 'varchar(16)';
        $charset = $this->getUTF8Charset();
        $collation = $this->getUTF8BinaryCollation();
        break;
      case 'text12':
        $column_type = 'varchar(12)';
        $charset = $this->getUTF8Charset();
        $collation = $this->getUTF8BinaryCollation();
        break;
      case 'text8':
        $column_type = 'varchar(8)';
        $charset = $this->getUTF8Charset();
        $collation = $this->getUTF8BinaryCollation();
        break;
      case 'text4':
        $column_type = 'varchar(4)';
        $charset = $this->getUTF8Charset();
        $collation = $this->getUTF8BinaryCollation();
        break;
      case 'text':
        $column_type = 'longtext';
        $charset = $this->getUTF8Charset();
        $collation = $this->getUTF8BinaryCollation();
        break;
      case 'bool':
        $column_type = 'tinyint(1)';
        break;
      case 'double':
        $column_type = 'double';
        break;
      case 'date':
        $column_type = 'date';
        break;
      default:
        $column_type = pht('<unknown>');
        $charset = pht('<unknown>');
        $collation = pht('<unknown>');
        break;
    }

    return array($column_type, $charset, $collation, $nullable, $auto);
  }

}
