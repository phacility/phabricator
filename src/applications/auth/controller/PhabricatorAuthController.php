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

  /**
   * Log a user into a web session and return an @{class:AphrontResponse} which
   * corresponds to continuing the login process.
   *
   * Normally, this is a redirect to the validation controller which makes sure
   * the user's cookies are set. However, event listeners can intercept this
   * event and do something else if they prefer.
   *
   * @param   PhabricatorUser   User to log the viewer in as.
   * @return  AphrontResponse   Response which continues the login process.
   */
  protected function loginUser(PhabricatorUser $user) {

    $response = $this->buildLoginValidateResponse($user);
    $session_type = 'web';

    $event_type = PhabricatorEventType::TYPE_AUTH_WILLLOGINUSER;
    $event_data = array(
      'user'        => $user,
      'type'        => $session_type,
      'response'    => $response,
      'shouldLogin' => true,
    );

    $event = id(new PhabricatorEvent($event_type, $event_data))
      ->setUser($user);
    PhutilEventEngine::dispatchEvent($event);

    $should_login = $event->getValue('shouldLogin');
    if ($should_login) {
      $session_key = $user->establishSession($session_type);

      // NOTE: We allow disabled users to login and roadblock them later, so
      // there's no check for users being disabled here.

      $request = $this->getRequest();
      $request->setCookie('phusr', $user->getUsername());
      $request->setCookie('phsid', $session_key);

      $this->clearRegistrationCookies();
    }

    return $event->getValue('response');
  }

  protected function clearRegistrationCookies() {
    $request = $this->getRequest();

    // Clear the registration key.
    $request->clearCookie('phreg');

    // Clear the client ID / OAuth state key.
    $request->clearCookie('phcid');
  }

  private function buildLoginValidateResponse(PhabricatorUser $user) {
    $validate_uri = new PhutilURI($this->getApplicationURI('validate/'));
    $validate_uri->setQueryParam('phusr', $user->getUsername());

    return id(new AphrontRedirectResponse())->setURI((string)$validate_uri);
  }

  protected function renderError($message) {
    return $this->renderErrorPage(
      pht('Authentication Error'),
      array(
        $message,
      ));
  }

}
