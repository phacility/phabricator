<?php

final class PhabricatorFileDropUploadController
  extends PhabricatorFileController {

  private $viewObject;

  public function setViewObject(AphrontAbstractAttachedFileView $view) {
    $this->viewObject = $view;
    return $this;
  }

  public function getViewObject() {
    if (!$this->viewObject) {
      $this->viewObject = new AphrontAttachedFileView();
    }
    return $this->viewObject;
  }

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

    $view = $this->getViewObject();
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
