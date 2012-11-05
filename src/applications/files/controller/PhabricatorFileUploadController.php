<?php

final class PhabricatorFileUploadController extends PhabricatorFileController {

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    if ($request->isFormPost()) {
      $file = PhabricatorFile::newFromPHPUpload(
        idx($_FILES, 'file'),
        array(
          'name'        => $request->getStr('name'),
          'authorPHID'  => $user->getPHID(),
        ));

      return id(new AphrontRedirectResponse())->setURI($file->getBestURI());
    }

    $panel = new PhabricatorFileUploadView();
    $panel->setUser($user);

    return $this->buildStandardPageResponse(
      array($panel),
      array(
        'title' => 'Upload File',
      ));
  }
}
