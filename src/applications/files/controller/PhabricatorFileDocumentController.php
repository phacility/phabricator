<?php

final class PhabricatorFileDocumentController
  extends PhabricatorFileController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $engine = id(new PhabricatorFileDocumentRenderingEngine())
      ->setRequest($request)
      ->setController($this);

    $viewer = $request->getViewer();

    $file_phid = $request->getURIData('phid');

    $file = id(new PhabricatorFileQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($file_phid))
      ->executeOne();
    if (!$file) {
      return $engine->newErrorResponse(
        pht(
          'This file ("%s") does not exist or could not be loaded.',
          $file_phid));
    }

    $ref = id(new PhabricatorDocumentRef())
      ->setFile($file);

    return $engine->newRenderResponse($ref);
  }

}
