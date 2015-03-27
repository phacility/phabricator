<?php

abstract class FileConduitAPIMethod extends ConduitAPIMethod {

  final public function getApplication() {
    return PhabricatorApplication::getByClass('PhabricatorFilesApplication');
  }

  protected function loadFileByPHID(PhabricatorUser $viewer, $file_phid) {
    $file = id(new PhabricatorFileQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($file_phid))
      ->executeOne();
    if (!$file) {
      throw new Exception(pht('No such file "%s"!', $file_phid));
    }

    return $file;
  }

  protected function loadFileChunks(
    PhabricatorUser $viewer,
    PhabricatorFile $file) {
    return $this->newChunkQuery($viewer, $file)
      ->execute();
  }

  protected function loadFileChunkForUpload(
    PhabricatorUser $viewer,
    PhabricatorFile $file,
    $start,
    $end) {

    $start = (int)$start;
    $end = (int)$end;

    $chunks = $this->newChunkQuery($viewer, $file)
      ->withByteRange($start, $end)
      ->execute();

    if (!$chunks) {
      throw new Exception(
        pht(
          'There are no file data chunks in byte range %d - %d.',
          $start,
          $end));
    }

    if (count($chunks) !== 1) {
      phlog($chunks);
      throw new Exception(
        pht(
          'There are multiple chunks in byte range %d - %d.',
          $start,
          $end));
    }

    $chunk = head($chunks);
    if ($chunk->getByteStart() != $start) {
      throw new Exception(
        pht(
          'Chunk start byte is %d, not %d.',
          $chunk->getByteStart(),
          $start));
    }

    if ($chunk->getByteEnd() != $end) {
      throw new Exception(
        pht(
          'Chunk end byte is %d, not %d.',
          $chunk->getByteEnd(),
          $end));
    }

    if ($chunk->getDataFilePHID()) {
      throw new Exception(
        pht(
          'Chunk has already been uploaded.'));
    }

    return $chunk;
  }

  protected function decodeBase64($data) {
    $data = base64_decode($data, $strict = true);
    if ($data === false) {
      throw new Exception(pht('Unable to decode base64 data!'));
    }
    return $data;
  }

  protected function loadAnyMissingChunk(
    PhabricatorUser $viewer,
    PhabricatorFile $file) {

    return $this->newChunkQuery($viewer, $file)
      ->withIsComplete(false)
      ->setLimit(1)
      ->execute();
  }

  private function newChunkQuery(
    PhabricatorUser $viewer,
    PhabricatorFile $file) {

    $engine = $file->instantiateStorageEngine();
    if (!$engine->isChunkEngine()) {
      throw new Exception(
        pht(
          'File "%s" does not have chunks!',
          $file->getPHID()));
    }

    return id(new PhabricatorFileChunkQuery())
      ->setViewer($viewer)
      ->withChunkHandles(array($file->getStorageHandle()));
  }


}
