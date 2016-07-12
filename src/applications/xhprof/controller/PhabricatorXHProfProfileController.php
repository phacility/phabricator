<?php

final class PhabricatorXHProfProfileController
  extends PhabricatorXHProfController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $phid = $request->getURIData('phid');

    $file = id(new PhabricatorFileQuery())
      ->setViewer($request->getUser())
      ->withPHIDs(array($phid))
      ->executeOne();
    if (!$file) {
      return new Aphront404Response();
    }

    $data = $file->loadFileData();
    try {
      $data = phutil_json_decode($data);
    } catch (PhutilJSONParserException $ex) {
      throw new PhutilProxyException(
        pht('Failed to unserialize XHProf profile!'),
        $ex);
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

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('%s Profile', $symbol));

    return $this->buildStandardPageResponse(
      array($crumbs, $view),
      array(
        'title' => pht('Profile'),
        'frame' => $is_framed,
      ));
  }
}
