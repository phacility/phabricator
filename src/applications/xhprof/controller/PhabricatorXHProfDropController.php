<?php

final class PhabricatorXHProfDropController
  extends PhabricatorXHProfController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    if (!$request->validateCSRF()) {
      return new Aphront400Response();
    }

    $cancel_uri = $this->getApplicationURI();

    $ids = $request->getStrList('h');
    if ($ids) {
      $files = id(new PhabricatorFileQuery())
        ->setViewer($viewer)
        ->withIDs($ids)
        ->setRaisePolicyExceptions(true)
        ->execute();
    } else {
      $files = array();
    }

    if (!$files) {
      return $this->newDialog()
        ->setTitle(pht('Nothing Uploaded'))
        ->appendParagraph(
          pht('Drag and drop .xhprof files to import them.'))
        ->addCancelButton($cancel_uri, pht('Done'));
    }

    $samples = array();
    foreach ($files as $file) {
      $sample = PhabricatorXHProfSample::initializeNewSample()
        ->setFilePHID($file->getPHID())
        ->setUserPHID($viewer->getPHID())
        ->save();

      $samples[] = $sample;
    }

    if (count($samples) == 1) {
      $event = head($samples);
      $next_uri = $event->getURI();
    } else {
      $next_uri = $this->getApplicationURI();
    }

    return id(new AphrontRedirectResponse())->setURI($next_uri);
  }

}
