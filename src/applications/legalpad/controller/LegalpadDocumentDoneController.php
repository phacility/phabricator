<?php

final class LegalpadDocumentDoneController extends LegalpadController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    return $this->newDialog()
      ->setTitle(pht('Verify Signature'))
      ->appendParagraph(
        pht(
          'Thank you for signing this document. Please check your email '.
          'to verify your signature and complete the process.'))
      ->addCancelButton('/', pht('Okay'));
  }

}
