<?php

final class PhabricatorXHProfProfileController
  extends PhabricatorXHProfController {

  private $phid;

  public function willProcessRequest(array $data) {
    $this->phid = $data['phid'];
  }

  public function processRequest() {
    $request = $this->getRequest();

    $file = id(new PhabricatorFileQuery())
      ->setViewer($request->getUser())
      ->withPHIDs(array($this->phid))
      ->executeOne();
    if (!$file) {
      return new Aphront404Response();
    }

    $data = $file->loadFileData();
    $data = unserialize($data);
    if (!$data) {
      throw new Exception("Failed to unserialize XHProf profile!");
    }

    $symbol = $request->getStr('symbol');

    $is_framed = $request->getBool('frame');

    if ($symbol) {
      $view = new PhabricatorXHProfProfileSymbolView();
      $view->setSymbol($symbol);
    } else {
      $view = new PhabricatorXHProfProfileTopLevelView();
      $view->setFile($file);
      $view->setLimit(100);
    }

    $view->setBaseURI($request->getRequestURI()->getPath());
    $view->setIsFramed($is_framed);
    $view->setProfileData($data);

    return $this->buildStandardPageResponse(
      $view,
      array(
        'title' => 'Profile',
        'frame' => $is_framed,
      ));
  }
}
