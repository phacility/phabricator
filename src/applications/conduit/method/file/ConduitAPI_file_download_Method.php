<?php

/**
 * @group conduit
 */
final class ConduitAPI_file_download_Method
  extends ConduitAPIMethod {

  public function getMethodDescription() {
    return "Download a file from the server.";
  }

  public function defineParamTypes() {
    return array(
      'phid' => 'required phid',
    );
  }

  public function defineReturnType() {
    return 'nonempty base64-bytes';
  }

  public function defineErrorTypes() {
    return array(
      'ERR-BAD-PHID' => 'No such file exists.',
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $phid = $request->getValue('phid');

    $file = id(new PhabricatorFile())->loadOneWhere(
      'phid = %s',
      $phid);
    if (!$file) {
      throw new ConduitException('ERR-BAD-PHID');
    }

    return base64_encode($file->loadFileData());
  }

}
