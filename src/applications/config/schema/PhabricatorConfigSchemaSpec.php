<?php

abstract class PhabricatorConfigSchemaSpec extends Phobject {

  private $server;
  private $utf8Charset;
  private $utf8Collation;

  public function setUTF8Collation($utf8_collation) {
    $this->utf8Collation = $utf8_collation;
    return $this;
  }

  public function getUTF8Collation() {
    return $this->utf8Collation;
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

  protected function buildLiskSchemata($base) {

    $objects = id(new PhutilSymbolLoader())
      ->setAncestorClass($base)
      ->loadObjects();

    foreach ($objects as $object) {
      $this->buildLiskObjectSchema($object);
    }
  }

  protected function buildTransactionSchema(
    PhabricatorApplicationTransaction $xaction,
    PhabricatorApplicationTransactionComment $comment = null) {

    $this->buildLiskObjectSchema($xaction);
    if ($comment) {
      $this->buildLiskObjectSchema($comment);
    }
  }

  protected function buildCustomFieldSchemata(
    PhabricatorLiskDAO $storage,
    array $indexes) {

    $this->buildLiskObjectSchema($storage);
    foreach ($indexes as $index) {
      $this->buildLiskObjectSchema($index);
    }
  }

  private function buildLiskObjectSchema(PhabricatorLiskDAO $object) {
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
      list($column_type, $charset, $collation, $nullable) = $details;

      $column = $this->newColumn($name)
        ->setDataType($type)
        ->setColumnType($column_type)
        ->setCharacterSet($charset)
        ->setCollation($collation)
        ->setNullable($nullable);

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
        'id' => 'id',
        'data' => 'text',
      ),
      array(
        'PRIMARY' => array(
          'columns' => array('id'),
          'unique' => true,
        ),
      ));
  }

  public function buildCounterSchema(PhabricatorLiskDAO $object) {
    $this->buildRawSchema(
      $object->getApplicationName(),
      PhabricatorLiskDAO::COUNTER_TABLE_NAME,
      array(
        'counterName' => 'text32',
        'counterValue' => 'id64',
      ),
      array(
        'PRIMARY' => array(
          'columns' => array('counterName'),
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
      ->setCollation($this->getUTF8Collation());
  }

  protected function getNamespacedDatabase($name) {
    $namespace = PhabricatorLiskDAO::getStorageNamespace();
    return $namespace.'_'.$name;
  }

  protected function newTable($name) {
    return id(new PhabricatorConfigTableSchema())
      ->setName($name)
      ->setCollation($this->getUTF8Collation());
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

    // If the type ends with "?", make the column nullable.
    $nullable = false;
    if (preg_match('/\?$/', $data_type)) {
      $nullable = true;
      $data_type = substr($data_type, 0, -1);
    }

    switch ($data_type) {
      case 'id':
      case 'epoch':
      case 'uint32':
        $column_type = 'int(10) unsigned';
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
        $column_type = 'varchar(64)';
        $charset = 'binary';
        $collation = 'binary';
        break;
      case 'bytes40':
        $column_type = 'char(40)';
        $charset = 'binary';
        $collation = 'binary';
        break;
      case 'bytes32':
        $column_type = 'char(32)';
        $charset = 'binary';
        $collation = 'binary';
        break;
      case 'bytes20':
        $column_type = 'char(20)';
        $charset = 'binary';
        $collation = 'binary';
        break;
      case 'bytes12':
        $column_type = 'char(12)';
        $charset = 'binary';
        $collation = 'binary';
        break;
      case 'bytes4':
        $column_type = 'char(4)';
        $charset = 'binary';
        $collation = 'binary';
        break;
      case 'bytes':
        $column_type = 'longblob';
        break;
      case 'text255':
        $column_type = 'varchar(255)';
        $charset = $this->getUTF8Charset();
        $collation = $this->getUTF8Collation();
        break;
      case 'text128':
        $column_type = 'varchar(128)';
        $charset = $this->getUTF8Charset();
        $collation = $this->getUTF8Collation();
        break;
      case 'text80':
        $column_type = 'varchar(80)';
        $charset = $this->getUTF8Charset();
        $collation = $this->getUTF8Collation();
        break;
      case 'text64':
        $column_type = 'varchar(64)';
        $charset = $this->getUTF8Charset();
        $collation = $this->getUTF8Collation();
        break;
      case 'text40':
        $column_type = 'varchar(40)';
        $charset = $this->getUTF8Charset();
        $collation = $this->getUTF8Collation();
        break;
      case 'text32':
        $column_type = 'varchar(32)';
        $charset = $this->getUTF8Charset();
        $collation = $this->getUTF8Collation();
        break;
      case 'text20':
        $column_type = 'varchar(20)';
        $charset = $this->getUTF8Charset();
        $collation = $this->getUTF8Collation();
        break;
      case 'text16':
        $column_type = 'varchar(16)';
        $charset = $this->getUTF8Charset();
        $collation = $this->getUTF8Collation();
        break;
      case 'text12':
        $column_type = 'varchar(12)';
        $charset = $this->getUTF8Charset();
        $collation = $this->getUTF8Collation();
        break;
      case 'text8':
        $column_type = 'varchar(8)';
        $charset = $this->getUTF8Charset();
        $collation = $this->getUTF8Collation();
        break;
      case 'text4':
        $column_type = 'varchar(4)';
        $charset = $this->getUTF8Charset();
        $collation = $this->getUTF8Collation();
        break;
      case 'text':
        $column_type = 'longtext';
        $charset = $this->getUTF8Charset();
        $collation = $this->getUTF8Collation();
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

    return array($column_type, $charset, $collation, $nullable);
  }

}
