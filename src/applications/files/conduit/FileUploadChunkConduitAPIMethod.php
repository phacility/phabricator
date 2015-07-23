<?php

final class FileUploadChunkConduitAPIMethod
  extends FileConduitAPIMethod {

  public function getAPIMethodName() {
    return 'file.uploadchunk';
  }

  public function getMethodDescription() {
    return pht('Upload a chunk of file data to the server.');
  }

  protected function defineParamTypes() {
    return array(
      'filePHID' => 'phid',
      'byteStart' => 'int',
      'data' => 'string',
      'dataEncoding' => 'string',
    );
  }

  protected function defineReturnType() {
    return 'void';
  }

  protected function execute(ConduitAPIRequest $request) {
    $viewer = $request->getUser();

    $file_phid = $request->getValue('filePHID');
    $file = $this->loadFileByPHID($viewer, $file_phid);

    $start = $request->getValue('byteStart');

    $data = $request->getValue('data');
    $encoding = $request->getValue('dataEncoding');
    switch ($encoding) {
      case 'base64':
        $data = $this->decodeBase64($data);
        break;
      case null:
        break;
      default:
        throw new Exception(pht('Unsupported data encoding.'));
    }
    $length = strlen($data);

    $chunk = $this->loadFileChunkForUpload(
      $viewer,
      $file,
      $start,
      $start + $length);

    // NOTE: These files have a view policy which prevents normal access. They
    // are only accessed through the storage engine.
    $chunk_data = PhabricatorFile::newFromFileData(
      $data,
      array(
        'name' => $file->getMonogram().'.chunk-'.$chunk->getID(),
        'viewPolicy' => PhabricatorPolicies::POLICY_NOONE,
      ));

    $chunk->setDataFilePHID($chunk_data->getPHID())->save();

    $missing = $this->loadAnyMissingChunk($viewer, $file);
    if (!$missing) {
      $file->setIsPartial(0)->save();
    }

    return null;
  }

}
