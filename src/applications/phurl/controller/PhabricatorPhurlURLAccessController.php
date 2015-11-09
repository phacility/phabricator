<?php

final class PhabricatorPhurlURLAccessController
  extends PhabricatorPhurlController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $id = $request->getURIData('id');
    $alias = $request->getURIData('alias');

    if ($id) {
      $url = id(new PhabricatorPhurlURLQuery())
        ->setViewer($viewer)
        ->withIDs(array($id))
        ->executeOne();
    } else if ($alias) {
      $url = id(new PhabricatorPhurlURLQuery())
        ->setViewer($viewer)
        ->withAliases(array($alias))
        ->executeOne();
    }

    if (!$url) {
      return new Aphront404Response();
    }

    if ($url->isValid()) {
      return id(new AphrontRedirectResponse())
        ->setURI($url->getLongURL())
        ->setIsExternal(true);
    } else {
      return id(new AphrontRedirectResponse())->setURI('/'.$url->getMonogram());
    }
  }
}
