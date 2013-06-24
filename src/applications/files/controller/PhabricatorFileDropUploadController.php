<?php

final class PhabricatorFileDropUploadController
  extends PhabricatorFileController {

  /**
   * @phutil-external-symbol class PhabricatorStartup
   */
  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    // NOTE: Throws if valid CSRF token is not present in the request.
    $request->validateCSRF();

    $data = PhabricatorStartup::getRawInput();
    $name = $request->getStr('name');

    $file = PhabricatorFile::newFromXHRUpload(
      $data,
      array(
        'name' => $request->getStr('name'),
        'authorPHID' => $user->getPHID(),
        'isExplicitUpload' => true,
      ));

    $view = new AphrontAttachedFileView();
    $view->setFile($file);

    return id(new AphrontAjaxResponse())->setContent(
      array(
        'id'   => $file->getID(),
        'phid' => $file->getPHID(),
        'html' => $view->render(),
        'uri'  => $file->getBestURI(),
      ));
  }

}
