<?php

final class Aphront404Response extends AphrontHTMLResponse {

  public function getHTTPResponseCode() {
    return 404;
  }

  public function buildResponseString() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $dialog = id(new AphrontDialogView())
      ->setUser($user)
      ->setTitle(pht('404 Not Found'))
      ->addCancelButton('/', pht('Focus'))
      ->appendParagraph(
        pht(
          'Do not dwell in the past, do not dream of the future, '.
          'concentrate the mind on the present moment.'));

    $view = id(new PhabricatorStandardPageView())
      ->setTitle(pht('404 Not Found'))
      ->setRequest($request)
      ->setDeviceReady(true)
      ->appendChild($dialog);

    return $view->render();
  }

}
