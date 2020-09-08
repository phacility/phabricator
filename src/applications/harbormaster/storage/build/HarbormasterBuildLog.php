<?php

final class HarbormasterBuildLog
  extends HarbormasterDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorDestructibleInterface,
    PhabricatorConduitResultInterface {

  protected $buildTargetPHID;
  protected $logSource;
  protected $logType;
  protected $duration;
  protected $live;
  protected $filePHID;
  protected $byteLength;
  protected $chunkFormat;
  protected $lineMap = array();

  private $buildTarget = self::ATTACHABLE;
  private $rope;
  private $isOpen;
  private $lock;

  const CHUNK_BYTE_LIMIT = 1048576;

  public function __construct() {
    $this->rope = new PhutilRope();
  }

  public function __destruct() {
    if ($this->isOpen) {
      $this->closeBuildLog();
    }

    if ($this->lock) {
      if ($this->lock->isLocked()) {
        $this->lock->unlock();
      }
    }
  }

  public static function initializeNewBuildLog(
    HarbormasterBuildTarget $build_target) {

    return id(new HarbormasterBuildLog())
      ->setBuildTargetPHID($build_target->getPHID())
      ->setDuration(null)
      ->setLive(1)
      ->setByteLength(0)
      ->setChunkFormat(HarbormasterBuildLogChunk::CHUNK_ENCODING_TEXT);
  }

  public function scheduleRebuild($force) {
    PhabricatorWorker::scheduleTask(
      'HarbormasterLogWorker',
      array(
        'logPHID' => $this->getPHID(),
        'force' => $force,
      ),
      array(
        'objectPHID' => $this->getPHID(),
      ));
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'lineMap' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        // T6203/NULLABILITY
        // It seems like these should be non-nullable? All logs should have a
        // source, etc.
        'logSource' => 'text255?',
        'logType' => 'text255?',
        'duration' => 'uint32?',

        'live' => 'bool',
        'filePHID' => 'phid?',
        'byteLength' => 'uint64',
        'chunkFormat' => 'text32',
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

  public function newChunkIterator() {
    return id(new HarbormasterBuildLogChunkIterator($this))
      ->setPageSize(8);
  }

  public function newDataIterator() {
    return $this->newChunkIterator()
      ->setAsString(true);
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

  public function loadData($offset, $length) {
    $end = ($offset + $length);

    $chunks = id(new HarbormasterBuildLogChunk())->loadAllWhere(
      'logID = %d AND headOffset < %d AND tailOffset >= %d
        ORDER BY headOffset ASC',
      $this->getID(),
      $end,
      $offset);

    // Make sure that whatever we read out of the database is a single
    // contiguous range which contains all of the requested bytes.
    $ranges = array();
    foreach ($chunks as $chunk) {
      $ranges[] = array(
        'head' => $chunk->getHeadOffset(),
        'tail' => $chunk->getTailOffset(),
      );
    }

    $ranges = isort($ranges, 'head');
    $ranges = array_values($ranges);
    $count = count($ranges);
    for ($ii = 0; $ii < ($count - 1); $ii++) {
      if ($ranges[$ii + 1]['head'] === $ranges[$ii]['tail']) {
        $ranges[$ii + 1]['head'] = $ranges[$ii]['head'];
        unset($ranges[$ii]);
      }
    }

    if (count($ranges) !== 1) {
      $display_ranges = array();
      foreach ($ranges as $range) {
        $display_ranges[] = pht(
          '(%d - %d)',
          $range['head'],
          $range['tail']);
      }

      if (!$display_ranges) {
        $display_ranges[] = pht('<null>');
      }

      throw new Exception(
        pht(
          'Attempt to load log bytes (%d - %d) failed: failed to '.
          'load a single contiguous range. Actual ranges: %s.',
          $offset,
          $end,
          implode('; ', $display_ranges)));
    }

    $range = head($ranges);
    if ($range['head'] > $offset || $range['tail'] < $end) {
      throw new Exception(
        pht(
          'Attempt to load log bytes (%d - %d) failed: the loaded range '.
          '(%d - %d) does not span the requested range.',
          $offset,
          $end,
          $range['head'],
          $range['tail']));
    }

    $parts = array();
    foreach ($chunks as $chunk) {
      $parts[] = $chunk->getChunkDisplayText();
    }
    $parts = implode('', $parts);

    $chop_head = ($offset - $range['head']);
    $chop_tail = ($range['tail'] - $end);

    if ($chop_head) {
      $parts = substr($parts, $chop_head);
    }

    if ($chop_tail) {
      $parts = substr($parts, 0, -$chop_tail);
    }

    return $parts;
  }

  public function getLineSpanningRange($min_line, $max_line) {
    $map = $this->getLineMap();
    if (!$map) {
      throw new Exception(pht('No line map.'));
    }

    $min_pos = 0;
    $min_line = 0;
    $max_pos = $this->getByteLength();
    list($map) = $map;
    foreach ($map as $marker) {
      list($offset, $count) = $marker;

      if ($count < $min_line) {
        if ($offset > $min_pos) {
          $min_pos = $offset;
          $min_line = $count;
        }
      }

      if ($count > $max_line) {
        $max_pos = min($max_pos, $offset);
        break;
      }
    }

    return array($min_pos, $max_pos, $min_line);
  }


  public function getReadPosition($read_offset) {
    $position = array(0, 0);

    $map = $this->getLineMap();
    if (!$map) {
      throw new Exception(pht('No line map.'));
    }

    list($map) = $map;
    foreach ($map as $marker) {
      list($offset, $count) = $marker;
      if ($offset > $read_offset) {
        break;
      }
      $position = $marker;
    }

    return $position;
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


  public function getURI() {
    $id = $this->getID();
    return "/harbormaster/log/view/{$id}/";
  }

  public function getRenderURI($lines) {
    if (strlen($lines)) {
      $lines = '$'.$lines;
    }

    $id = $this->getID();
    return "/harbormaster/log/render/{$id}/{$lines}";
  }


/* -(  Chunks  )------------------------------------------------------------- */


  public function canCompressLog() {
    return function_exists('gzdeflate');
  }

  public function compressLog() {
    $this->processLog(HarbormasterBuildLogChunk::CHUNK_ENCODING_GZIP);
  }

  public function decompressLog() {
    $this->processLog(HarbormasterBuildLogChunk::CHUNK_ENCODING_TEXT);
  }

  private function processLog($mode) {
    if (!$this->getLock()->isLocked()) {
      throw new Exception(
        pht(
          'You can not process build log chunks unless the log lock is '.
          'held.'));
    }

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

    $offset = 0;
    foreach ($chunks as $chunk) {
      $rope->append($chunk->getChunkDisplayText());
      $chunk->delete();

      while ($rope->getByteLength() > $byte_limit) {
        $offset += $this->writeEncodedChunk($rope, $offset, $byte_limit, $mode);
      }
    }

    while ($rope->getByteLength()) {
      $offset += $this->writeEncodedChunk($rope, $offset, $byte_limit, $mode);
    }

    $this
      ->setChunkFormat($mode)
      ->save();

    $this->saveTransaction();
  }

  private function writeEncodedChunk(
    PhutilRope $rope,
    $offset,
    $length,
    $mode) {

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

    $this->writeChunk($mode, $offset, $size, $data);

    $rope->removeBytesFromHead($size);

    return $size;
  }

  private function writeChunk($encoding, $offset, $raw_size, $data) {
    $head_offset = $offset;
    $tail_offset = $offset + $raw_size;

    return id(new HarbormasterBuildLogChunk())
      ->setLogID($this->getID())
      ->setEncoding($encoding)
      ->setHeadOffset($head_offset)
      ->setTailOffset($tail_offset)
      ->setSize($raw_size)
      ->setChunk($data)
      ->save();
  }


/* -(  Writing  )------------------------------------------------------------ */


  public function getLock() {
    if (!$this->lock) {
      $phid = $this->getPHID();
      $phid_key = PhabricatorHash::digestToLength($phid, 14);
      $lock_key = "build.log({$phid_key})";
      $lock = PhabricatorGlobalLock::newLock($lock_key);
      $this->lock = $lock;
    }

    return $this->lock;
  }


  public function openBuildLog() {
    if ($this->isOpen) {
      throw new Exception(pht('This build log is already open!'));
    }

    $is_new = !$this->getID();
    if ($is_new) {
      $this->save();
    }

    $this->getLock()->lock();
    $this->isOpen = true;

    $this->reload();

    if (!$this->getLive()) {
      $this->setLive(1)->save();
    }

    return $this;
  }

  public function closeBuildLog($forever = true) {
    if (!$this->isOpen) {
      throw new Exception(
        pht(
          'You must openBuildLog() before you can closeBuildLog().'));
    }

    $this->flush();

    if ($forever) {
      $start = $this->getDateCreated();
      $now = PhabricatorTime::getNow();

      $this
        ->setDuration($now - $start)
        ->setLive(0)
        ->save();
    }

    $this->getLock()->unlock();
    $this->isOpen = false;

    if ($forever) {
      $this->scheduleRebuild(false);
    }

    return $this;
  }

  public function append($content) {
    if (!$this->isOpen) {
      throw new Exception(
        pht(
          'You must openBuildLog() before you can append() content to '.
          'the log.'));
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

      $this->openTransaction();
        if ($append_id) {
          queryfx(
            $conn_w,
            'UPDATE %T SET
                chunk = CONCAT(chunk, %B),
                size = %d,
                tailOffset = headOffset + %d
              WHERE
                id = %d',
            $chunk_table,
            $append_data,
            $prefix_size + $data_size,
            $prefix_size + $data_size,
            $append_id);
        } else {
          $this->writeChunk(
            $encoding_text,
            $this->getByteLength(),
            $data_size,
            $append_data);
        }

        $this->updateLineMap($append_data);

        $this->save();
      $this->saveTransaction();

      $rope->removeBytesFromHead($data_size);
    }
  }

  public function updateLineMap($append_data, $marker_distance = null) {
    $this->byteLength += strlen($append_data);

    if (!$marker_distance) {
      $marker_distance = (self::CHUNK_BYTE_LIMIT / 2);
    }

    if (!$this->lineMap) {
      $this->lineMap = array(
        array(),
        0,
        0,
        null,
      );
    }

    list($map, $map_bytes, $line_count, $prefix) = $this->lineMap;

    $buffer = $append_data;

    if ($prefix) {
      $prefix = base64_decode($prefix);
      $buffer = $prefix.$buffer;
    }

    if ($map) {
      list($last_marker, $last_count) = last($map);
    } else {
      $last_marker = 0;
      $last_count = 0;
    }

    $max_utf8_width = 8;
    $next_marker = $last_marker + $marker_distance;

    $pos = 0;
    $len = strlen($buffer);
    while (true) {
      // If we only have a few bytes left in the buffer, leave it as a prefix
      // for next time.
      if (($len - $pos) <= ($max_utf8_width * 2)) {
        $prefix = substr($buffer, $pos);
        break;
      }

      // The next slice we're going to look at is the smaller of:
      //
      //   - the number of bytes we need to make it to the next marker; or
      //   - all the bytes we have left, minus one.

      $slice_length = min(
        ($marker_distance - $map_bytes),
        ($len - $pos) - 1);

      // We don't slice all the way to the end for two reasons.

      // First, we want to avoid slicing immediately after a "\r" if we don't
      // know what the next character is, because we want to make sure to
      // count "\r\n" as a single newline, rather than counting the "\r" as
      // a newline and then later counting the "\n" as another newline.

      // Second, we don't want to slice in the middle of a UTF8 character if
      // we can help it. We may not be able to avoid this, since the whole
      // buffer may just be binary data, but in most cases we can backtrack
      // a little bit and try to make it out of emoji or other legitimate
      // multibyte UTF8 characters which appear in the log.

      $min_width = max(1, $slice_length - $max_utf8_width);
      while ($slice_length >= $min_width) {
        $here = $buffer[$pos + ($slice_length - 1)];
        $next = $buffer[$pos + ($slice_length - 1) + 1];

        // If this is "\r" and the next character is "\n", extend the slice
        // to include the "\n". Otherwise, we're fine to slice here since we
        // know we're not in the middle of a UTF8 character.
        if ($here === "\r") {
          if ($next === "\n") {
            $slice_length++;
          }
          break;
        }

        // If the next character is 0x7F or lower, or between 0xC2 and 0xF4,
        // we're not slicing in the middle of a UTF8 character.
        $ord = ord($next);
        if ($ord <= 0x7F || ($ord >= 0xC2 && $ord <= 0xF4)) {
          break;
        }

        $slice_length--;
      }

      $slice = substr($buffer, $pos, $slice_length);
      $pos += $slice_length;

      $map_bytes += $slice_length;

      // Count newlines in the slice. This goofy approach is meaningfully
      // faster than "preg_match_all()" or "preg_split()". See PHI766.
      $n_rn = substr_count($slice, "\r\n");
      $n_r = substr_count($slice, "\r");
      $n_n = substr_count($slice, "\n");
      $line_count += ($n_rn) + ($n_r - $n_rn) + ($n_n - $n_rn);

      if ($map_bytes >= ($marker_distance - $max_utf8_width)) {
        $map[] = array(
          $last_marker + $map_bytes,
          $last_count + $line_count,
        );

        $last_count = $last_count + $line_count;
        $line_count = 0;

        $last_marker = $last_marker + $map_bytes;
        $map_bytes = 0;

        $next_marker = $last_marker + $marker_distance;
      }
    }

    $this->lineMap = array(
      $map,
      $map_bytes,
      $line_count,
      base64_encode($prefix),
    );

    return $this;
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
      'Users must be able to see a build target to view its build log.');
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {
    $this->destroyFile($engine);
    $this->destroyChunks();
    $this->delete();
  }

  public function destroyFile(PhabricatorDestructionEngine $engine = null) {
    if (!$engine) {
      $engine = new PhabricatorDestructionEngine();
    }

    $file_phid = $this->getFilePHID();
    if ($file_phid) {
      $viewer = $engine->getViewer();
      $file = id(new PhabricatorFileQuery())
        ->setViewer($viewer)
        ->withPHIDs(array($file_phid))
        ->executeOne();
      if ($file) {
        $engine->destroyObject($file);
      }
    }

    $this->setFilePHID(null);

    return $this;
  }

  public function destroyChunks() {
    $chunk = new HarbormasterBuildLogChunk();
    $conn = $chunk->establishConnection('w');

    // Just delete the chunks directly so we don't have to pull the data over
    // the wire for large logs.
    queryfx(
      $conn,
      'DELETE FROM %T WHERE logID = %d',
      $chunk->getTableName(),
      $this->getID());

    return $this;
  }


/* -(  PhabricatorConduitResultInterface  )---------------------------------- */


  public function getFieldSpecificationsForConduit() {
    return array(
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('buildTargetPHID')
        ->setType('phid')
        ->setDescription(pht('Build target this log is attached to.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('byteLength')
        ->setType('int')
        ->setDescription(pht('Length of the log in bytes.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('filePHID')
        ->setType('phid?')
        ->setDescription(pht('A file containing the log data.')),
    );
  }

  public function getFieldValuesForConduit() {
    return array(
      'buildTargetPHID' => $this->getBuildTargetPHID(),
      'byteLength' => (int)$this->getByteLength(),
      'filePHID' => $this->getFilePHID(),
    );
  }

  public function getConduitSearchAttachments() {
    return array();
  }

}
