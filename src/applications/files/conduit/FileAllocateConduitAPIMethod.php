<?php

final class FileAllocateConduitAPIMethod
  extends FileConduitAPIMethod {

  public function getAPIMethodName() {
    return 'file.allocate';
  }

  public function getMethodDescription() {
    return pht('Prepare to upload a file.');
  }

  public function defineParamTypes() {
    return array(
      'name' => 'string',
      'contentLength' => 'int',
      'contentHash' => 'optional string',
      'viewPolicy' => 'optional string',

      // TODO: Remove this, it's just here to make testing easier.
      'forceChunking' => 'optional bool',
    );
  }

  public function defineReturnType() {
    return 'map<string, wild>';
  }

  public function defineErrorTypes() {
    return array();
  }

  protected function execute(ConduitAPIRequest $request) {
    $viewer = $request->getUser();

    $hash = $request->getValue('contentHash');
    $name = $request->getValue('name');
    $view_policy = $request->getValue('viewPolicy');
    $content_length = $request->getValue('contentLength');

    $force_chunking = $request->getValue('forceChunking');

    $properties = array(
      'name' => $name,
      'authorPHID' => $viewer->getPHID(),
      'viewPolicy' => $view_policy,
      'isExplicitUpload' => true,
    );

    if ($hash) {
      $file = PhabricatorFile::newFileFromContentHash(
        $hash,
        $properties);

      if ($file) {
        return array(
          'upload' => false,
          'filePHID' => $file->getPHID(),
        );
      }

      $chunked_hash = PhabricatorChunkedFileStorageEngine::getChunkedHash(
        $viewer,
        $hash);
      $file = id(new PhabricatorFileQuery())
        ->setViewer($viewer)
        ->withContentHashes(array($chunked_hash))
        ->executeOne();

      if ($file) {
        return array(
          'upload' => (bool)$file->getIsPartial(),
          'filePHID' => $file->getPHID(),
        );
      }
    }

    $engines = PhabricatorFileStorageEngine::loadStorageEngines(
      $content_length);
    if ($engines) {

      if ($force_chunking) {
        foreach ($engines as $key => $engine) {
          if (!$engine->isChunkEngine()) {
            unset($engines[$key]);
          }
        }
      }

      // Pick the first engine. If the file is small enough to fit into a
      // single engine without chunking, this will be a non-chunk engine and
      // we'll just tell the client to upload the file.
      $engine = head($engines);
      if ($engine) {
        if (!$engine->isChunkEngine()) {
          return array(
            'upload' => true,
            'filePHID' => null,
          );
        }

        // Otherwise, this is a large file and we need to perform a chunked
        // upload.

        $chunk_properties = $properties;

        if ($hash) {
          $chunk_properties += array(
            'chunkedHash' => $chunked_hash,
          );
        }

        $file = $engine->allocateChunks($content_length, $chunk_properties);

        return array(
          'upload' => true,
          'filePHID' => $file->getPHID(),
        );
      }
    }

    // None of the storage engines can accept this file.

    return array(
      'upload' => false,
      'filePHID' => null,
    );
  }

}
