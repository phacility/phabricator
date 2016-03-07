<?php

final class HarbormasterBuildLog
  extends HarbormasterDAO
  implements PhabricatorPolicyInterface {

  protected $buildTargetPHID;
  protected $logSource;
  protected $logType;
  protected $duration;
  protected $live;

  private $buildTarget = self::ATTACHABLE;
  private $rope;
  private $isOpen;

  const CHUNK_BYTE_LIMIT = 102400;

  public function __construct() {
    $this->rope = new PhutilRope();
  }

  public function __destruct() {
    if ($this->isOpen) {
      $this->closeBuildLog();
    }
  }

  public static function initializeNewBuildLog(
    HarbormasterBuildTarget $build_target) {

    return id(new HarbormasterBuildLog())
      ->setBuildTargetPHID($build_target->getPHID())
      ->setDuration(null)
      ->setLive(0);
  }

  public function openBuildLog() {
    if ($this->isOpen) {
      throw new Exception(pht('This build log is already open!'));
    }

    $this->isOpen = true;

    return $this
      ->setLive(1)
      ->save();
  }

  public function closeBuildLog() {
    if (!$this->isOpen) {
      throw new Exception(pht('This build log is not open!'));
    }

    if ($this->canCompressLog()) {
      $this->compressLog();
    }

    $start = $this->getDateCreated();
    $now = PhabricatorTime::getNow();

    return $this
      ->setDuration($now - $start)
      ->setLive(0)
      ->save();
  }


  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        // T6203/NULLABILITY
        // It seems like these should be non-nullable? All logs should have a
        // source, etc.
        'logSource' => 'text255?',
        'logType' => 'text255?',
        'duration' => 'uint32?',

        'live' => 'bool',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_buildtarget' => array(
          'columns' => array('buildTargetPHID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      HarbormasterBuildLogPHIDType::TYPECONST);
  }

  public function attachBuildTarget(HarbormasterBuildTarget $build_target) {
    $this->buildTarget = $build_target;
    return $this;
  }

  public function getBuildTarget() {
    return $this->assertAttached($this->buildTarget);
  }

  public function getName() {
    return pht('Build Log');
  }

  public function append($content) {
    if (!$this->getLive()) {
      throw new PhutilInvalidStateException('openBuildLog');
    }

    $content = (string)$content;

    $this->rope->append($content);
    $this->flush();

    return $this;
  }

  private function flush() {

    // TODO: Maybe don't flush more than a couple of times per second. If a
    // caller writes a single character over and over again, we'll currently
    // spend a lot of time flushing that.

    $chunk_table = id(new HarbormasterBuildLogChunk())->getTableName();
    $chunk_limit = self::CHUNK_BYTE_LIMIT;
    $encoding_text = HarbormasterBuildLogChunk::CHUNK_ENCODING_TEXT;

    $rope = $this->rope;

    while (true) {
      $length = $rope->getByteLength();
      if (!$length) {
        break;
      }

      $conn_w = $this->establishConnection('w');
      $last = $this->loadLastChunkInfo();

      $can_append =
        ($last) &&
        ($last['encoding'] == $encoding_text) &&
        ($last['size'] < $chunk_limit);
      if ($can_append) {
        $append_id = $last['id'];
        $prefix_size = $last['size'];
      } else {
        $append_id = null;
        $prefix_size = 0;
      }

      $data_limit = ($chunk_limit - $prefix_size);
      $append_data = $rope->getPrefixBytes($data_limit);
      $data_size = strlen($append_data);

      if ($append_id) {
        queryfx(
          $conn_w,
          'UPDATE %T SET chunk = CONCAT(chunk, %B), size = %d WHERE id = %d',
          $chunk_table,
          $append_data,
          $prefix_size + $data_size,
          $append_id);
      } else {
        $this->writeChunk($encoding_text, $data_size, $append_data);
      }

      $rope->removeBytesFromHead($data_size);
    }
  }

  public function newChunkIterator() {
    return id(new HarbormasterBuildLogChunkIterator($this))
      ->setPageSize(32);
  }

  private function loadLastChunkInfo() {
    $chunk_table = new HarbormasterBuildLogChunk();
    $conn_w = $chunk_table->establishConnection('w');

    return queryfx_one(
      $conn_w,
      'SELECT id, size, encoding FROM %T WHERE logID = %d
        ORDER BY id DESC LIMIT 1',
      $chunk_table->getTableName(),
      $this->getID());
  }

  public function getLogText() {
    // TODO: Remove this method since it won't scale for big logs.

    $all_chunks = $this->newChunkIterator();

    $full_text = array();
    foreach ($all_chunks as $chunk) {
      $full_text[] = $chunk->getChunkDisplayText();
    }

    return implode('', $full_text);
  }

  private function canCompressLog() {
    return function_exists('gzdeflate');
  }

  public function compressLog() {
    $this->processLog(HarbormasterBuildLogChunk::CHUNK_ENCODING_GZIP);
  }

  public function decompressLog() {
    $this->processLog(HarbormasterBuildLogChunk::CHUNK_ENCODING_TEXT);
  }

  private function processLog($mode) {
    $chunks = $this->newChunkIterator();

    // NOTE: Because we're going to insert new chunks, we need to stop the
    // iterator once it hits the final chunk which currently exists. Otherwise,
    // it may start consuming chunks we just wrote and run forever.
    $last = $this->loadLastChunkInfo();
    if ($last) {
      $chunks->setRange(null, $last['id']);
    }

    $byte_limit = self::CHUNK_BYTE_LIMIT;
    $rope = new PhutilRope();

    $this->openTransaction();

    foreach ($chunks as $chunk) {
      $rope->append($chunk->getChunkDisplayText());
      $chunk->delete();

      while ($rope->getByteLength() > $byte_limit) {
        $this->writeEncodedChunk($rope, $byte_limit, $mode);
      }
    }

    while ($rope->getByteLength()) {
      $this->writeEncodedChunk($rope, $byte_limit, $mode);
    }

    $this->saveTransaction();
  }

  private function writeEncodedChunk(PhutilRope $rope, $length, $mode) {
    $data = $rope->getPrefixBytes($length);
    $size = strlen($data);

    switch ($mode) {
      case HarbormasterBuildLogChunk::CHUNK_ENCODING_TEXT:
        // Do nothing.
        break;
      case HarbormasterBuildLogChunk::CHUNK_ENCODING_GZIP:
        $data = gzdeflate($data);
        if ($data === false) {
          throw new Exception(pht('Failed to gzdeflate() log data!'));
        }
        break;
      default:
        throw new Exception(pht('Unknown chunk encoding "%s"!', $mode));
    }

    $this->writeChunk($mode, $size, $data);

    $rope->removeBytesFromHead($size);
  }

  private function writeChunk($encoding, $raw_size, $data) {
    return id(new HarbormasterBuildLogChunk())
      ->setLogID($this->getID())
      ->setEncoding($encoding)
      ->setSize($raw_size)
      ->setChunk($data)
      ->save();
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    return $this->getBuildTarget()->getPolicy($capability);
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return $this->getBuildTarget()->hasAutomaticCapability(
      $capability,
      $viewer);
  }

  public function describeAutomaticCapability($capability) {
    return pht(
      "Users must be able to see a build target to view it's build log.");
  }


}
