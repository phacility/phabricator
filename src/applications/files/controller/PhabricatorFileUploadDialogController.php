<?php

final class PhabricatorFileUploadDialogController
  extends PhabricatorFileController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    return $this->newDialog()
      ->setTitle(pht('Upload File'))
      ->appendChild(pht(
        'To add files, drag and drop them into the comment text area.'))
      ->addCancelButton('/', pht('Close'));

  }

}
