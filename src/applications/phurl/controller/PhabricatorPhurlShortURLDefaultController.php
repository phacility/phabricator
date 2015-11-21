<?php

final class PhabricatorPhurlShortURLDefaultController
  extends PhabricatorPhurlController {

  public function shouldRequireLogin() {
    return false;
  }

  public function handleRequest(AphrontRequest $request) {
    $dialog = $this->newDialog()
      ->setTitle(pht('Invalid URL'))
      ->appendParagraph(
        pht('This domain can only be used to open URLs'.
          ' shortened using the Phurl application. The'.
          ' URL you are trying to access does not have'.
          ' a Phurl URL associated with it.'));

    return id(new AphrontDialogResponse())
      ->setDialog($dialog)
      ->setHTTPResponseCode(404);
  }
}
