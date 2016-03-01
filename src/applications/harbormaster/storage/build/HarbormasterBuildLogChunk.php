<?php

final class HarbormasterBuildLogChunk
  extends HarbormasterDAO {

  protected $logID;
  protected $encoding;
  protected $size;
  protected $chunk;


  /**
   * The log is encoded as plain text.
   */
  const CHUNK_ENCODING_TEXT = 'text';

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

  public function getChunkDisplayText() {
    $data = $this->getChunk();
    $encoding = $this->getEncoding();

    switch ($encoding) {
      case self::CHUNK_ENCODING_TEXT:
        // Do nothing, data is already plaintext.
        break;
      default:
        throw new Exception(
          pht('Unknown log chunk encoding ("%s")!', $encoding));
    }

    return $data;
  }


}
