<?php

final class HarbormasterBuildLogChunk
  extends HarbormasterDAO {

  protected $logID;
  protected $encoding;
  protected $headOffset;
  protected $tailOffset;
  protected $size;
  protected $chunk;

  const CHUNK_ENCODING_TEXT = 'text';
  const CHUNK_ENCODING_GZIP = 'gzip';

  protected function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_BINARY => array(
        'chunk' => true,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'logID' => 'id',
        'encoding' => 'text32',
        'headOffset' => 'uint64',
        'tailOffset' => 'uint64',

        // T6203/NULLABILITY
        // Both the type and nullability of this column are crazily wrong.
        'size' => 'uint32?',

        'chunk' => 'bytes',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_offset' => array(
          'columns' => array('logID', 'headOffset', 'tailOffset'),
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
      case self::CHUNK_ENCODING_GZIP:
        $data = gzinflate($data);
        if ($data === false) {
          throw new Exception(pht('Unable to inflate log chunk!'));
        }
        break;
      default:
        throw new Exception(
          pht('Unknown log chunk encoding ("%s")!', $encoding));
    }

    return $data;
  }


}
