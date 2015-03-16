<?php

final class FileQueryChunksConduitAPIMethod
  extends FileConduitAPIMethod {

  public function getAPIMethodName() {
    return 'file.querychunks';
  }

  public function getMethodDescription() {
    return pht('Get information about file chunks.');
  }

  public function defineParamTypes() {
    return array(
      'filePHID' => 'phid',
    );
  }

  public function defineReturnType() {
    return 'list<wild>';
  }

  public function defineErrorTypes() {
    return array();
  }

  protected function execute(ConduitAPIRequest $request) {
    $viewer = $request->getUser();

    $file_phid = $request->getValue('filePHID');
    $file = $this->loadFileByPHID($viewer, $file_phid);
    $chunks = $this->loadFileChunks($viewer, $file);

    $results = array();
    foreach ($chunks as $chunk) {
      $results[] = array(
        'byteStart' => $chunk->getByteStart(),
        'byteEnd' => $chunk->getByteEnd(),
        'complete' => (bool)$chunk->getDataFilePHID(),
      );
    }

    return $results;
  }

}
