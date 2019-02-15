<?php

final class HarbormasterString
  extends HarbormasterDAO {

  protected $stringIndex;
  protected $stringValue;

  protected function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_COLUMN_SCHEMA => array(
        'stringIndex' => 'bytes12',
        'stringValue' => 'text',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_string' => array(
          'columns' => array('stringIndex'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

  public static function newIndex($string) {
    $index = PhabricatorHash::digestForIndex($string);

    $table = new self();
    $conn = $table->establishConnection('w');

    queryfx(
      $conn,
      'INSERT IGNORE INTO %R (stringIndex, stringValue) VALUES (%s, %s)',
      $table,
      $index,
      $string);

    return $index;
  }

  public static function newIndexMap(array $indexes) {
    $table = new self();
    $conn = $table->establishConnection('r');

    $rows = queryfx_all(
      $conn,
      'SELECT stringIndex, stringValue FROM %R WHERE stringIndex IN (%Ls)',
      $table,
      $indexes);

    return ipull($rows, 'stringValue', 'stringIndex');
  }

}
