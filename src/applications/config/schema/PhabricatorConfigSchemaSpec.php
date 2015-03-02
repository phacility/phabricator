<?php

abstract class PhabricatorConfigSchemaSpec extends Phobject {

  private $server;
  private $utf8Charset;
  private $utf8BinaryCollation;
  private $utf8SortingCollation;

  const DATATYPE_UNKNOWN = '<unknown>';

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

    $is_binary = ($this->getUTF8Charset() == 'binary');
    $matches = null;
    if (preg_match('/^(fulltext|sort|text)(\d+)?\z/', $data_type, $matches)) {

      // Limit the permitted column lengths under the theory that it would
      // be nice to eventually reduce this to a small set of standard lengths.

      static $valid_types = array(
        'text255' => true,
        'text160' => true,
        'text128' => true,
        'text80' => true,
        'text64' => true,
        'text40' => true,
        'text32' => true,
        'text20' => true,
        'text16' => true,
        'text12' => true,
        'text8' => true,
        'text4' => true,
        'text' => true,
        'sort255' => true,
        'sort128' => true,
        'sort64' => true,
        'sort32' => true,
        'sort' => true,
        'fulltext' => true,
      );

      if (empty($valid_types[$data_type])) {
        throw new Exception(pht('Unknown column type "%s"!', $data_type));
      }

      $type = $matches[1];
      $size = idx($matches, 2);

      switch ($type) {
        case 'text':
          if ($is_binary) {
            if ($size) {
              $column_type = 'varbinary('.$size.')';
            } else {
              $column_type = 'longblob';
            }
          } else {
            if ($size) {
              $column_type = 'varchar('.$size.')';
            } else {
              $column_type = 'longtext';
            }
          }
          break;
        case 'sort':
          if ($size) {
            $column_type = 'varchar('.$size.')';
          } else {
            $column_type = 'longtext';
          }
          break;
        case 'fulltext';
          // MySQL (at least, under MyISAM) refuses to create a FULLTEXT index
          // on a LONGBLOB column. We'd also lose case insensitivity in search.
          // Force this column to utf8 collation. This will truncate results
          // with 4-byte UTF characters in their text, but work reasonably in
          // the majority of cases.
          $column_type = 'longtext';
          break;
      }

      switch ($type) {
        case 'text':
          if ($is_binary) {
            // We leave collation and character set unspecified in order to
            // generate valid SQL.
          } else {
            $charset = $this->getUTF8Charset();
            $collation = $this->getUTF8BinaryCollation();
          }
          break;
        case 'sort':
        case 'fulltext':
          if ($is_binary) {
            $charset = 'utf8';
          } else {
            $charset = $this->getUTF8Charset();
          }
          $collation = $this->getUTF8SortingCollation();
          break;
      }
    } else {
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
          $column_type = self::DATATYPE_UNKNOWN;
          $charset = self::DATATYPE_UNKNOWN;
          $collation = self::DATATYPE_UNKNOWN;
          break;
      }
    }

    return array($column_type, $charset, $collation, $nullable, $auto);
  }

}
