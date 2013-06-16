<?php

abstract class PhabricatorAuthController extends PhabricatorController {

  public function buildStandardPageResponse($view, array $data) {
    $page = $this->buildStandardPageView();

    $page->setApplicationName(pht('Login'));
    $page->setBaseURI('/login/');
    $page->setTitle(idx($data, 'title'));
    $page->appendChild($view);

    $response = new AphrontWebpageResponse();
    return $response->setContent($page->render());
  }

  protected function renderErrorPage($title, array $messages) {
    $view = new AphrontErrorView();
    $view->setTitle($title);
    $view->setErrors($messages);

    return $this->buildApplicationPage(
      $view,
      array(
        'title' => $title,
        'device' => true,
        'dust' => true,
      ));

  }

  protected function establishWebSession(PhabricatorUser $user) {
    $session_key = $user->establishSession('web');

    $request = $this->getRequest();

    // NOTE: We allow disabled users to login and roadblock them later, so
    // there's no check for users being disabled here.

    $request->setCookie('phusr', $user->getUsername());
    $request->setCookie('phsid', $session_key);
    $request->clearCookie('phreg');
  }

  protected function buildLoginValidateResponse(PhabricatorUser $user) {
    $validate_uri = new PhutilURI($this->getApplicationURI('validate/'));
    $validate_uri->setQueryParam('phusr', $user->getUsername());

    return id(new AphrontRedirectResponse())->setURI((string)$validate_uri);
  }

}
