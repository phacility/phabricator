<?php

final class PhabricatorFileUploadDialogController
  extends PhabricatorFileController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $dialog = id(new AphrontDialogView())
      ->setUser($user)
      ->setTitle(pht('Upload File'))
      ->appendChild(pht(
        'To add files, drag and drop them into the comment text area.'))
      ->addCancelButton('/', pht('Close'));

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
