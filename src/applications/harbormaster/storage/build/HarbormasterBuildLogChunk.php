<?php

final class HarbormasterBuildLogChunk
  extends HarbormasterDAO {

  protected $logID;
  protected $encoding;
  protected $size;
  protected $chunk;

  protected function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_COLUMN_SCHEMA => array(
        'logID' => 'id',
        'encoding' => 'text32',

        // T6203/NULLABILITY
        // Both the type and nullability of this column are crazily wrong.
        'size' => 'uint32?',

        'chunk' => 'bytes',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_log' => array(
          'columns' => array('logID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

}
