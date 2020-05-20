<?php

final class PhabricatorConfigColumnSchema
  extends PhabricatorConfigStorageSchema {

  private $characterSet;
  private $collation;
  private $columnType;
  private $dataType;
  private $nullable;
  private $autoIncrement;

  public function setAutoIncrement($auto_increment) {
    $this->autoIncrement = $auto_increment;
    return $this;
  }

  public function getAutoIncrement() {
    return $this->autoIncrement;
  }

  public function setNullable($nullable) {
    $this->nullable = $nullable;
    return $this;
  }

  public function getNullable() {
    return $this->nullable;
  }

  public function setColumnType($column_type) {
    $this->columnType = $column_type;
    return $this;
  }

  public function getColumnType() {
    return $this->columnType;
  }

  protected function getSubschemata() {
    return array();
  }

  public function setDataType($data_type) {
    $this->dataType = $data_type;
    return $this;
  }

  public function getDataType() {
    return $this->dataType;
  }

  public function setCollation($collation) {
    $this->collation = $collation;
    return $this;
  }

  public function getCollation() {
    return $this->collation;
  }

  public function setCharacterSet($character_set) {
    $this->characterSet = $character_set;
    return $this;
  }

  public function getCharacterSet() {
    return $this->characterSet;
  }

  public function hasSameColumnTypeAs(PhabricatorConfigColumnSchema $other) {
    $u_type = $this->getColumnType();
    $v_type = $other->getColumnType();

    if ($u_type === $v_type) {
      return true;
    }

    // See T13536. Display widths for integers were deprecated in MySQL 8.0.17
    // and removed from some display contexts in or around 8.0.19. Older
    // MySQL versions will report "int(10)"; newer versions will report "int".
    // Accept these as equivalent.

    static $map = array(
      'int(10) unsigned' => 'int unsigned',
      'int(10)' => 'int',
      'bigint(20) unsigned' => 'bigint unsigned',
      'bigint(20)' => 'bigint',
    );

    if (isset($map[$u_type])) {
      $u_type = $map[$u_type];
    }

    if (isset($map[$v_type])) {
      $v_type = $map[$v_type];
    }

    return ($u_type === $v_type);
  }

  public function getKeyByteLength($prefix = null) {
    $type = $this->getColumnType();

    $matches = null;
    if (preg_match('/^(?:var)?char\((\d+)\)$/', $type, $matches)) {
      // For utf8mb4, each character requires 4 bytes.
      $size = (int)$matches[1];
      if ($prefix && $prefix < $size) {
        $size = $prefix;
      }
      return $size * 4;
    }

    $matches = null;
    if (preg_match('/^(?:var)?binary\((\d+)\)$/', $type, $matches)) {
      // binary()/varbinary() store fixed-length binary data, so their size
      // is always the column size.
      $size = (int)$matches[1];
      if ($prefix && $prefix < $size) {
        $size = $prefix;
      }
      return $size;
    }

    // The "long..." types are arbitrarily long, so just use a big number to
    // get the point across. In practice, these should always index only a
    // prefix.
    if ($type == 'longtext') {
      $size = (1 << 16);
      if ($prefix && $prefix < $size) {
        $size = $prefix;
      }
      return $size * 4;
    }

    if ($type == 'longblob') {
      $size = (1 << 16);
      if ($prefix && $prefix < $size) {
        $size = $prefix;
      }
      return $size * 1;
    }

    switch ($type) {
      case 'int(10) unsigned':
        return 4;
    }

    // TODO: Build this out to catch overlong indexes.

    return 0;
  }

  protected function compareToSimilarSchema(
    PhabricatorConfigStorageSchema $expect) {

    $issues = array();

    $type_unknown = PhabricatorConfigSchemaSpec::DATATYPE_UNKNOWN;
    if ($expect->getColumnType() == $type_unknown) {
      $issues[] = self::ISSUE_UNKNOWN;
    } else {
      if ($this->getCharacterSet() != $expect->getCharacterSet()) {
        $issues[] = self::ISSUE_CHARSET;
      }

      if ($this->getCollation() != $expect->getCollation()) {
        $issues[] = self::ISSUE_COLLATION;
      }

      if (!$this->hasSameColumnTypeAs($expect)) {
        $issues[] = self::ISSUE_COLUMNTYPE;
      }

      if ($this->getNullable() !== $expect->getNullable()) {
        $issues[] = self::ISSUE_NULLABLE;
      }

      if ($this->getAutoIncrement() !== $expect->getAutoIncrement()) {
        $issues[] = self::ISSUE_AUTOINCREMENT;
      }
    }

    return $issues;
  }

  public function newEmptyClone() {
    $clone = clone $this;
    return $clone;
  }

}
