<?php

final class FileUploadHashConduitAPIMethod extends FileConduitAPIMethod {

  public function getAPIMethodName() {
    // TODO: Deprecate this in favor of `file.allocate`.
    return 'file.uploadhash';
  }

  public function getMethodDescription() {
    return pht('Upload a file to the server using content hash.');
  }

  protected function defineParamTypes() {
    return array(
      'hash' => 'required nonempty string',
      'name' => 'required nonempty string',
    );
  }

  protected function defineReturnType() {
    return 'phid or null';
  }

  protected function execute(ConduitAPIRequest $request) {
    $hash = $request->getValue('hash');
    $name = $request->getValue('name');
    $user = $request->getUser();

    $file = PhabricatorFile::newFileFromContentHash(
      $hash,
      array(
        'name' => $name,
        'authorPHID' => $user->getPHID(),
      ));

    if ($file) {
      return $file->getPHID();
    }
    return $file;
  }

}
