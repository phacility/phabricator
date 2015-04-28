<?php

final class FileDownloadConduitAPIMethod extends FileConduitAPIMethod {

  public function getAPIMethodName() {
    return 'file.download';
  }

  public function getMethodDescription() {
    return 'Download a file from the server.';
  }

  protected function defineParamTypes() {
    return array(
      'phid' => 'required phid',
    );
  }

  protected function defineReturnType() {
    return 'nonempty base64-bytes';
  }

  protected function defineErrorTypes() {
    return array(
      'ERR-BAD-PHID' => 'No such file exists.',
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $phid = $request->getValue('phid');

    $file = id(new PhabricatorFileQuery())
      ->setViewer($request->getUser())
      ->withPHIDs(array($phid))
      ->executeOne();
    if (!$file) {
      throw new ConduitException('ERR-BAD-PHID');
    }

    return base64_encode($file->loadFileData());
  }

}
