<?php

final class Aphront404Response extends AphrontHTMLResponse {

  public function getHTTPResponseCode() {
    return 404;
  }

  public function buildResponseString() {
    $request = $this->getRequest();
    $viewer = $request->getViewer();

    $dialog = id(new AphrontDialogView())
      ->setViewer($viewer)
      ->setTitle(pht('404 Not Found'))
      ->addCancelButton('/', pht('Return to Charted Waters'))
      ->appendParagraph(
        pht(
          'You arrive at your destination, but there is nothing here.'))
      ->appendParagraph(
        pht(
          'Perhaps the real treasure was the friends you made '.
          'along the way.'));

    $view = id(new PhabricatorStandardPageView())
      ->setTitle(pht('404 Not Found'))
      ->setRequest($request)
      ->setDeviceReady(true)
      ->appendChild($dialog);

    return $view->render();
  }

}
