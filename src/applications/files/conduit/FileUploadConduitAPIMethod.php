<?php

final class FileUploadConduitAPIMethod extends FileConduitAPIMethod {

  public function getAPIMethodName() {
    return 'file.upload';
  }

  public function getMethodDescription() {
    return pht('Upload a file to the server.');
  }

  protected function defineParamTypes() {
    return array(
      'data_base64' => 'required nonempty base64-bytes',
      'name' => 'optional string',
      'viewPolicy' => 'optional valid policy string or <phid>',
      'canCDN' => 'optional bool',
    );
  }

  protected function defineReturnType() {
    return 'nonempty guid';
  }

  protected function execute(ConduitAPIRequest $request) {
    $viewer = $request->getUser();

    $name = $request->getValue('name');
    $can_cdn = (bool)$request->getValue('canCDN');
    $view_policy = $request->getValue('viewPolicy');

    $data = $request->getValue('data_base64');
    $data = $this->decodeBase64($data);

    $params = array(
      'authorPHID' => $viewer->getPHID(),
      'canCDN' => $can_cdn,
      'isExplicitUpload' => true,
    );

    if ($name !== null) {
      $params['name'] = $name;
    }

    if ($view_policy !== null) {
      $params['viewPolicy'] = $view_policy;
    }

    $file = PhabricatorFile::newFromFileData($data, $params);

    return $file->getPHID();
  }

}
