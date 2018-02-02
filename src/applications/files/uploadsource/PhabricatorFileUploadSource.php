<?php

abstract class PhabricatorFileUploadSource
  extends Phobject {

  private $name;
  private $relativeTTL;
  private $viewPolicy;
  private $mimeType;
  private $authorPHID;

  private $rope;
  private $data;
  private $shouldChunk;
  private $didRewind;
  private $totalBytesWritten = 0;
  private $totalBytesRead = 0;
  private $byteLimit = 0;

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getName() {
    return $this->name;
  }

  public function setRelativeTTL($relative_ttl) {
    $this->relativeTTL = $relative_ttl;
    return $this;
  }

  public function getRelativeTTL() {
    return $this->relativeTTL;
  }

  public function setViewPolicy($view_policy) {
    $this->viewPolicy = $view_policy;
    return $this;
  }

  public function getViewPolicy() {
    return $this->viewPolicy;
  }

  public function setByteLimit($byte_limit) {
    $this->byteLimit = $byte_limit;
    return $this;
  }

  public function getByteLimit() {
    return $this->byteLimit;
  }

  public function setMIMEType($mime_type) {
    $this->mimeType = $mime_type;
    return $this;
  }

  public function getMIMEType() {
    return $this->mimeType;
  }

  public function setAuthorPHID($author_phid) {
    $this->authorPHID = $author_phid;
    return $this;
  }

  public function getAuthorPHID() {
    return $this->authorPHID;
  }

  public function uploadFile() {
    if (!$this->shouldChunkFile()) {
      return $this->writeSingleFile();
    } else {
      return $this->writeChunkedFile();
    }
  }

  private function getDataIterator() {
    if (!$this->data) {
      $this->data = $this->newDataIterator();
    }
    return $this->data;
  }

  private function getRope() {
    if (!$this->rope) {
      $this->rope = new PhutilRope();
    }
    return $this->rope;
  }

  abstract protected function newDataIterator();
  abstract protected function getDataLength();

  private function readFileData() {
    $data = $this->getDataIterator();

    if (!$this->didRewind) {
      $data->rewind();
      $this->didRewind = true;
    } else {
      if ($data->valid()) {
        $data->next();
      }
    }

    if (!$data->valid()) {
      return false;
    }

    $read_bytes = $data->current();
    $this->totalBytesRead += strlen($read_bytes);

    if ($this->byteLimit && ($this->totalBytesRead > $this->byteLimit)) {
      throw new PhabricatorFileUploadSourceByteLimitException();
    }

    $rope = $this->getRope();
    $rope->append($read_bytes);

    return true;
  }

  private function shouldChunkFile() {
    if ($this->shouldChunk !== null) {
      return $this->shouldChunk;
    }

    $threshold = PhabricatorFileStorageEngine::getChunkThreshold();

    if ($threshold === null) {
      // If there are no chunk engines available, we clearly can't chunk the
      // file.
      $this->shouldChunk = false;
    } else {
      // If we don't know how large the file is, we're going to read some data
      // from it until we know whether it's a small file or not. This will give
      // us enough information to make a decision about chunking.
      $length = $this->getDataLength();
      if ($length === null) {
        $rope = $this->getRope();
        while ($this->readFileData()) {
          $length = $rope->getByteLength();
          if ($length > $threshold) {
            break;
          }
        }
      }

      $this->shouldChunk = ($length > $threshold);
    }

    return $this->shouldChunk;
  }

  private function writeSingleFile() {
    while ($this->readFileData()) {
      // Read the entire file.
    }

    $rope = $this->getRope();
    $data = $rope->getAsString();

    $parameters = $this->getNewFileParameters();

    return PhabricatorFile::newFromFileData($data, $parameters);
  }

  private function writeChunkedFile() {
    $engine = $this->getChunkEngine();

    $parameters = $this->getNewFileParameters();

    $data_length = $this->getDataLength();
    if ($data_length !== null) {
      $length = $data_length;
    } else {
      $length = 0;
    }

    $file = PhabricatorFile::newChunkedFile($engine, $length, $parameters);
    $file->saveAndIndex();

    $rope = $this->getRope();

    // Read the source, writing chunks as we get enough data.
    while ($this->readFileData()) {
      while (true) {
        $rope_length = $rope->getByteLength();
        if ($rope_length < $engine->getChunkSize()) {
          break;
        }
        $this->writeChunk($file, $engine);
      }
    }

    // If we have extra bytes at the end, write them. Note that it's possible
    // that we have more than one chunk of bytes left if the read was very
    // fast.
    while ($rope->getByteLength()) {
      $this->writeChunk($file, $engine);
    }

    $file->setIsPartial(0);
    if ($data_length === null) {
      $file->setByteSize($this->getTotalBytesWritten());
    }
    $file->save();

    return $file;
  }

  private function writeChunk(
    PhabricatorFile $file,
    PhabricatorFileStorageEngine $engine) {

    $offset = $this->getTotalBytesWritten();
    $max_length = $engine->getChunkSize();
    $rope = $this->getRope();

    $data = $rope->getPrefixBytes($max_length);
    $actual_length = strlen($data);
    $rope->removeBytesFromHead($actual_length);

    $params = array(
      'name' => $file->getMonogram().'.chunk-'.$offset,
      'viewPolicy' => PhabricatorPolicies::POLICY_NOONE,
      'chunk' => true,
    );

    // If this isn't the initial chunk, provide a dummy MIME type so we do not
    // try to detect it. See T12857.
    if ($offset > 0) {
      $params['mime-type'] = 'application/octet-stream';
    }

    $chunk_data = PhabricatorFile::newFromFileData($data, $params);

    $chunk = PhabricatorFileChunk::initializeNewChunk(
      $file->getStorageHandle(),
      $offset,
      $offset + $actual_length);

    $chunk
      ->setDataFilePHID($chunk_data->getPHID())
      ->save();

    $this->setTotalBytesWritten($offset + $actual_length);

    return $chunk;
  }

  private function getNewFileParameters() {
    $parameters = array(
      'name' => $this->getName(),
      'viewPolicy' => $this->getViewPolicy(),
    );

    $ttl = $this->getRelativeTTL();
    if ($ttl !== null) {
      $parameters['ttl.relative'] = $ttl;
    }

    $mime_type = $this->getMimeType();
    if ($mime_type !== null) {
      $parameters['mime-type'] = $mime_type;
    }

    $author_phid = $this->getAuthorPHID();
    if ($author_phid !== null) {
      $parameters['authorPHID'] = $author_phid;
    }

    return $parameters;
  }

  private function getChunkEngine() {
    $chunk_engines = PhabricatorFileStorageEngine::loadWritableChunkEngines();
    if (!$chunk_engines) {
      throw new Exception(
        pht(
          'Unable to upload file: this server is not configured with any '.
          'storage engine which can store large files.'));
    }

    return head($chunk_engines);
  }

  private function setTotalBytesWritten($total_bytes_written) {
    $this->totalBytesWritten = $total_bytes_written;
    return $this;
  }

  private function getTotalBytesWritten() {
    return $this->totalBytesWritten;
  }

}
