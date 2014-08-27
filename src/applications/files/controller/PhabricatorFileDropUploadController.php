<?php

final class PhabricatorFileDropUploadController
  extends PhabricatorFileController {

  /**
   * @phutil-external-symbol class PhabricatorStartup
   */
  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    // NOTE: Throws if valid CSRF token is not present in the request.
    $request->validateCSRF();

    $data = PhabricatorStartup::getRawInput();
    $name = $request->getStr('name');

    // If there's no explicit view policy, make it very restrictive by default.
    // This is the correct policy for files dropped onto objects during
    // creation, comment and edit flows.

    $view_policy = $request->getStr('viewPolicy');
    if (!$view_policy) {
      $view_policy = $viewer->getPHID();
    }

    $file = PhabricatorFile::newFromXHRUpload(
      $data,
      array(
        'name' => $request->getStr('name'),
        'authorPHID' => $viewer->getPHID(),
        'viewPolicy' => $view_policy,
        'isExplicitUpload' => true,
      ));

    return id(new AphrontAjaxResponse())->setContent(
      array(
        'id'   => $file->getID(),
        'phid' => $file->getPHID(),
        'uri'  => $file->getBestURI(),
      ));
  }

}
