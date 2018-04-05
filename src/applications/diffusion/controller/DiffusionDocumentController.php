<?php

final class DiffusionDocumentController extends DiffusionController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $response = $this->loadDiffusionContext();
    if ($response) {
      return $response;
    }

    $drequest = $this->getDiffusionRequest();

    $engine = id(new DiffusionDocumentRenderingEngine())
      ->setRequest($request)
      ->setDiffusionRequest($drequest)
      ->setController($this);

    $viewer = $this->getViewer();
    $request = $this->getRequest();
    $repository = $drequest->getRepository();

    $file_phid = $request->getStr('filePHID');

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
