<?php

final class PhabricatorFlagDeleteController extends PhabricatorFlagController {


  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $flag = id(new PhabricatorFlag())->load($id);
    if (!$flag) {
      return new Aphront404Response();
    }

    if ($flag->getOwnerPHID() != $viewer->getPHID()) {
      return new Aphront400Response();
    }

    $flag->delete();

    return id(new AphrontReloadResponse())->setURI('/flag/');
  }

}
