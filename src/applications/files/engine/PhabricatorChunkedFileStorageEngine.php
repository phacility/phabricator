<?php

final class PhabricatorChunkedFileStorageEngine
  extends PhabricatorFileStorageEngine {

  public function getEngineIdentifier() {
    return 'chunks';
  }

  public function getEnginePriority() {
    return 60000;
  }

  /**
   * We can write chunks if we have at least one valid storage engine
   * underneath us.
   */
  public function canWriteFiles() {
    return (bool)$this->getWritableEngine();
  }

  public function hasFilesizeLimit() {
    return false;
  }

  public function isChunkEngine() {
    return true;
  }

  public function writeFile($data, array $params) {
    // The chunk engine does not support direct writes.
    throw new PhutilMethodNotImplementedException();
  }

  public function readFile($handle) {
    // This is inefficient, but makes the API work as expected.
    $chunks = $this->loadAllChunks($handle, true);

    $buffer = '';
    foreach ($chunks as $chunk) {
      $data_file = $chunk->getDataFile();
      if (!$data_file) {
        throw new Exception(pht('This file data is incomplete!'));
      }

      $buffer .= $chunk->getDataFile()->loadFileData();
    }

    return $buffer;
  }

  public function deleteFile($handle) {
    $engine = new PhabricatorDestructionEngine();
    $chunks = $this->loadAllChunks($handle, true);
    foreach ($chunks as $chunk) {
      $engine->destroyObject($chunk);
    }
  }

  private function loadAllChunks($handle, $need_files) {
    $chunks = id(new PhabricatorFileChunkQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withChunkHandles(array($handle))
      ->needDataFiles($need_files)
      ->execute();

    $chunks = msort($chunks, 'getByteStart');

    return $chunks;
  }

  /**
   * Compute a chunked file hash for the viewer.
   *
   * We can not currently compute a real hash for chunked file uploads (because
   * no process sees all of the file data).
   *
   * We also can not trust the hash that the user claims to have computed. If
   * we trust the user, they can upload some `evil.exe` and claim it has the
   * same file hash as `good.exe`. When another user later uploads the real
   * `good.exe`, we'll just create a reference to the existing `evil.exe`. Users
   * who download `good.exe` will then receive `evil.exe`.
   *
   * Instead, we rehash the user's claimed hash with account secrets. This
   * allows users to resume file uploads, but not collide with other users.
   *
   * Ideally, we'd like to be able to verify hashes, but this is complicated
   * and time consuming and gives us a fairly small benefit.
   *
   * @param PhabricatorUser Viewing user.
   * @param string Claimed file hash.
   * @return string Rehashed file hash.
   */
  public static function getChunkedHash(PhabricatorUser $viewer, $hash) {
    if (!$viewer->getPHID()) {
      throw new Exception(
        pht('Unable to compute chunked hash without real viewer!'));
    }

    $input = $viewer->getAccountSecret().':'.$hash.':'.$viewer->getPHID();
    return self::getChunkedHashForInput($input);
  }

  public static function getChunkedHashForInput($input) {
    $rehash = PhabricatorHash::digest($input);

    // Add a suffix to identify this as a chunk hash.
    $rehash = substr($rehash, 0, -2).'-C';

    return $rehash;
  }

  public function allocateChunks($length, array $properties) {
    $file = PhabricatorFile::newChunkedFile($this, $length, $properties);

    $chunk_size = $this->getChunkSize();

    $handle = $file->getStorageHandle();

    $chunks = array();
    for ($ii = 0; $ii < $length; $ii += $chunk_size) {
      $chunks[] = PhabricatorFileChunk::initializeNewChunk(
        $handle,
        $ii,
        min($ii + $chunk_size, $length));
    }

    $file->openTransaction();
      foreach ($chunks as $chunk) {
        $chunk->save();
      }
      $file->save();
    $file->saveTransaction();

    return $file;
  }

  /**
   * Find a storage engine which is suitable for storing chunks.
   *
   * This engine must be a writable engine, have a filesize limit larger than
   * the chunk limit, and must not be a chunk engine itself.
   */
  private function getWritableEngine() {
    // NOTE: We can't just load writable engines or we'll loop forever.
    $engines = parent::loadAllEngines();

    foreach ($engines as $engine) {
      if ($engine->isChunkEngine()) {
        continue;
      }

      if ($engine->isTestEngine()) {
        continue;
      }

      if (!$engine->canWriteFiles()) {
        continue;
      }

      if ($engine->hasFilesizeLimit()) {
        if ($engine->getFilesizeLimit() < $this->getChunkSize()) {
          continue;
        }
      }

      return true;
    }

    return false;
  }

  public function getChunkSize() {
    return (4 * 1024 * 1024);
  }

  public function getFileDataIterator(PhabricatorFile $file, $begin, $end) {
    $chunks = id(new PhabricatorFileChunkQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withChunkHandles(array($file->getStorageHandle()))
      ->withByteRange($begin, $end)
      ->needDataFiles(true)
      ->execute();

    return new PhabricatorFileChunkIterator($chunks, $begin, $end);
  }

}
