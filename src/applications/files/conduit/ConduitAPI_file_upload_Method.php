<?php

/**
 * @group conduit
 */
final class ConduitAPI_file_upload_Method extends ConduitAPI_file_Method {

  public function getMethodDescription() {
    return "Upload a file to the server.";
  }

  public function defineParamTypes() {
    return array(
      'data_base64' => 'required nonempty base64-bytes',
      'name' => 'optional string',
    );
  }

  public function defineReturnType() {
    return 'nonempty guid';
  }

  public function defineErrorTypes() {
    return array(
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $data = $request->getValue('data_base64');
    $name = $request->getValue('name');
    $data = base64_decode($data, $strict = true);
    $user = $request->getUser();

    $file = PhabricatorFile::newFromFileData(
      $data,
      array(
        'name' => $name,
        'authorPHID' => $user->getPHID(),
      ));
    return $file->getPHID();
  }

}
