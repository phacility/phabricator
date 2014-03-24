<?php

abstract class PhabricatorController extends AphrontController {

  private $handles;

  public function shouldRequireLogin() {
    return true;
  }

  public function shouldRequireAdmin() {
    return false;
  }

  public function shouldRequireEnabledUser() {
    return true;
  }

  public function shouldAllowPublic() {
    return false;
  }

  public function shouldRequireEmailVerification() {
    return PhabricatorUserEmail::isEmailVerificationRequired();
  }

  public function shouldAllowRestrictedParameter($parameter_name) {
    return false;
  }

  public function willBeginExecution() {
    $request = $this->getRequest();

    if ($request->getUser()) {
      // NOTE: Unit tests can set a user explicitly. Normal requests are not
      // permitted to do this.
      PhabricatorTestCase::assertExecutingUnitTests();
      $user = $request->getUser();
    } else {
      $user = new PhabricatorUser();
      $session_engine = new PhabricatorAuthSessionEngine();

      $phsid = $request->getCookie(PhabricatorCookies::COOKIE_SESSION);
      if (strlen($phsid)) {
        $session_user = $session_engine->loadUserForSession(
          PhabricatorAuthSession::TYPE_WEB,
          $phsid);
        if ($session_user) {
          $user = $session_user;
        }
      } else {
        // If the client doesn't have a session token, generate an anonymous
        // session. This is used to provide CSRF protection to logged-out users.
        $phsid = $session_engine->establishSession(
          PhabricatorAuthSession::TYPE_WEB,
          null);

        // This may be a resource request, in which case we just don't set
        // the cookie.
        if ($request->canSetCookies()) {
          $request->setCookie(PhabricatorCookies::COOKIE_SESSION, $phsid);
        }
      }


      if (!$user->isLoggedIn()) {
        $user->attachAlternateCSRFString(PhabricatorHash::digest($phsid));
      }

      $request->setUser($user);
    }

    $translation = $user->getTranslation();
    if ($translation &&
        $translation != PhabricatorEnv::getEnvConfig('translation.provider')) {
      $translation = newv($translation, array());
      PhutilTranslator::getInstance()
        ->setLanguage($translation->getLanguage())
        ->addTranslations($translation->getTranslations());
    }

    $preferences = $user->loadPreferences();
    if (PhabricatorEnv::getEnvConfig('darkconsole.enabled')) {
      $dark_console = PhabricatorUserPreferences::PREFERENCE_DARK_CONSOLE;
      if ($preferences->getPreference($dark_console) ||
         PhabricatorEnv::getEnvConfig('darkconsole.always-on')) {
        $console = new DarkConsoleCore();
        $request->getApplicationConfiguration()->setConsole($console);
      }
    }

    // NOTE: We want to set up the user first so we can render a real page
    // here, but fire this before any real logic.
    $restricted = array(
      'code',
    );
    foreach ($restricted as $parameter) {
      if ($request->getExists($parameter)) {
        if (!$this->shouldAllowRestrictedParameter($parameter)) {
          throw new Exception(
            pht(
              'Request includes restricted parameter "%s", but this '.
              'controller ("%s") does not whitelist it. Refusing to '.
              'serve this request because it might be part of a redirection '.
              'attack.',
              $parameter,
              get_class($this)));
        }
      }
    }

    if ($this->shouldRequireEnabledUser()) {
      if ($user->isLoggedIn() && !$user->getIsApproved()) {
        $controller = new PhabricatorAuthNeedsApprovalController($request);
        return $this->delegateToController($controller);
      }
      if ($user->getIsDisabled()) {
        $controller = new PhabricatorDisabledUserController($request);
        return $this->delegateToController($controller);
      }
    }

    $event = new PhabricatorEvent(
      PhabricatorEventType::TYPE_CONTROLLER_CHECKREQUEST,
      array(
        'request' => $request,
        'controller' => $this,
      ));
    $event->setUser($user);
    PhutilEventEngine::dispatchEvent($event);
    $checker_controller = $event->getValue('controller');
    if ($checker_controller != $this) {
      return $this->delegateToController($checker_controller);
    }

    if ($this->shouldRequireLogin()) {
      // This actually means we need either:
      //   - a valid user, or a public controller; and
      //   - permission to see the application.

      $auth_class = 'PhabricatorApplicationAuth';
      $auth_application = PhabricatorApplication::getByClass($auth_class);

      $allow_public = $this->shouldAllowPublic() &&
                      PhabricatorEnv::getEnvConfig('policy.allow-public');

      // If this controller isn't public, and the user isn't logged in, require
      // login.
      if (!$allow_public && !$user->isLoggedIn()) {
        $login_controller = new PhabricatorAuthStartController($request);
        $this->setCurrentApplication($auth_application);
        return $this->delegateToController($login_controller);
      }

      if ($user->isLoggedIn()) {
        if ($this->shouldRequireEmailVerification()) {
          if (!$user->getIsEmailVerified()) {
            $controller = new PhabricatorMustVerifyEmailController($request);
            $this->setCurrentApplication($auth_application);
            return $this->delegateToController($controller);
          }
        }
      }

      // If the user doesn't have access to the application, don't let them use
      // any of its controllers. We query the application in order to generate
      // a policy exception if the viewer doesn't have permission.

      $application = $this->getCurrentApplication();
      if ($application) {
        id(new PhabricatorApplicationQuery())
          ->setViewer($user)
          ->withPHIDs(array($application->getPHID()))
          ->executeOne();
      }
    }

    // NOTE: We do this last so that users get a login page instead of a 403
    // if they need to login.
    if ($this->shouldRequireAdmin() && !$user->getIsAdmin()) {
      return new Aphront403Response();
    }

  }

  public function buildStandardPageView() {
    $view = new PhabricatorStandardPageView();
    $view->setRequest($this->getRequest());
    $view->setController($this);
    return $view;
  }

  public function buildStandardPageResponse($view, array $data) {
    $page = $this->buildStandardPageView();
    $page->appendChild($view);
    $response = new AphrontWebpageResponse();
    $response->setContent($page->render());
    return $response;
  }

  public function getApplicationURI($path = '') {
    if (!$this->getCurrentApplication()) {
      throw new Exception("No application!");
    }
    return $this->getCurrentApplication()->getApplicationURI($path);
  }

  public function buildApplicationPage($view, array $options) {
    $page = $this->buildStandardPageView();

    $title = PhabricatorEnv::getEnvConfig('phabricator.serious-business') ?
      'Phabricator' :
      pht('Bacon Ice Cream for Breakfast');

    $application = $this->getCurrentApplication();
    $page->setTitle(idx($options, 'title', $title));
    if ($application) {
      $page->setApplicationName($application->getName());
      if ($application->getTitleGlyph()) {
        $page->setGlyph($application->getTitleGlyph());
      }
    }

    if (!($view instanceof AphrontSideNavFilterView)) {
      $nav = new AphrontSideNavFilterView();
      $nav->appendChild($view);
      $view = $nav;
    }

    $user = $this->getRequest()->getUser();
    $view->setUser($user);

    $page->appendChild($view);

    $object_phids = idx($options, 'pageObjects', array());
    if ($object_phids) {
      $page->appendPageObjects($object_phids);
      foreach ($object_phids as $object_phid) {
        PhabricatorFeedStoryNotification::updateObjectNotificationViews(
          $user,
          $object_phid);
      }
    }

    if (idx($options, 'device')) {
      $page->setDeviceReady(true);
    }

    $page->setShowChrome(idx($options, 'chrome', true));

    $application_menu = $this->buildApplicationMenu();
    if ($application_menu) {
      $page->setApplicationMenu($application_menu);
    }

    $response = new AphrontWebpageResponse();
    return $response->setContent($page->render());
  }

  public function didProcessRequest($response) {
    // If a bare DialogView is returned, wrap it in a DialogResponse.
    if ($response instanceof AphrontDialogView) {
      $response = id(new AphrontDialogResponse())->setDialog($response);
    }

    $request = $this->getRequest();
    $response->setRequest($request);

    $seen = array();
    while ($response instanceof AphrontProxyResponse) {

      $hash = spl_object_hash($response);
      if (isset($seen[$hash])) {
        $seen[] = get_class($response);
        throw new Exception(
          "Cycle while reducing proxy responses: ".
          implode(' -> ', $seen));
      }
      $seen[$hash] = get_class($response);

      $response = $response->reduceProxyResponse();
    }

    if ($response instanceof AphrontDialogResponse) {
      if (!$request->isAjax()) {
        $dialog = $response->getDialog();

        $title = $dialog->getTitle();
        $short = $dialog->getShortTitle();

        $crumbs = $this->buildApplicationCrumbs();
        $crumbs->addTextCrumb(coalesce($short, $title));

        $page_content = array(
          $crumbs,
          $response->buildResponseString(),
        );

        $view = id(new PhabricatorStandardPageView())
          ->setRequest($request)
          ->setController($this)
          ->setTitle($title)
          ->appendChild($page_content);

        $response = id(new AphrontWebpageResponse())
          ->setContent($view->render())
          ->setHTTPResponseCode($response->getHTTPResponseCode());
      } else {
        $response->getDialog()->setIsStandalone(true);

        return id(new AphrontAjaxResponse())
          ->setContent(array(
            'dialog' => $response->buildResponseString(),
          ));
      }
    } else if ($response instanceof AphrontRedirectResponse) {
      if ($request->isAjax()) {
        return id(new AphrontAjaxResponse())
          ->setContent(
            array(
              'redirect' => $response->getURI(),
            ));
      }
    }

    return $response;
  }

  protected function getHandle($phid) {
    if (empty($this->handles[$phid])) {
      throw new Exception(
        "Attempting to access handle which wasn't loaded: {$phid}");
    }
    return $this->handles[$phid];
  }

  protected function loadHandles(array $phids) {
    $phids = array_filter($phids);
    $this->handles = $this->loadViewerHandles($phids);
    return $this;
  }

  protected function getLoadedHandles() {
    return $this->handles;
  }

  protected function loadViewerHandles(array $phids) {
    return id(new PhabricatorHandleQuery())
      ->setViewer($this->getRequest()->getUser())
      ->withPHIDs($phids)
      ->execute();
  }


  /**
   * Render a list of links to handles, identified by PHIDs. The handles must
   * already be loaded.
   *
   * @param   list<phid>  List of PHIDs to render links to.
   * @param   string      Style, one of "\n" (to put each item on its own line)
   *                      or "," (to list items inline, separated by commas).
   * @return  string      Rendered list of handle links.
   */
  protected function renderHandlesForPHIDs(array $phids, $style = "\n") {
    $style_map = array(
      "\n"  => phutil_tag('br'),
      ','   => ', ',
    );

    if (empty($style_map[$style])) {
      throw new Exception("Unknown handle list style '{$style}'!");
    }

    return implode_selected_handle_links($style_map[$style],
      $this->getLoadedHandles(),
      array_filter($phids));
  }

  protected function buildApplicationMenu() {
    return null;
  }

  protected function buildApplicationCrumbs() {

    $crumbs = array();

    $application = $this->getCurrentApplication();
    if ($application) {
      $sprite = $application->getIconName();
      if (!$sprite) {
        $sprite = 'application';
      }

      $crumbs[] = id(new PhabricatorCrumbView())
        ->setHref($this->getApplicationURI())
        ->setIcon($sprite);
    }

    $view = new PhabricatorCrumbsView();
    foreach ($crumbs as $crumb) {
      $view->addCrumb($crumb);
    }

    return $view;
  }

  protected function hasApplicationCapability($capability) {
    return PhabricatorPolicyFilter::hasCapability(
      $this->getRequest()->getUser(),
      $this->getCurrentApplication(),
      $capability);
  }

  protected function requireApplicationCapability($capability) {
    PhabricatorPolicyFilter::requireCapability(
      $this->getRequest()->getUser(),
      $this->getCurrentApplication(),
      $capability);
  }

  protected function explainApplicationCapability(
    $capability,
    $positive_message,
    $negative_message) {

    $can_act = $this->hasApplicationCapability($capability);
    if ($can_act) {
      $message = $positive_message;
      $icon_name = 'enable-grey';
    } else {
      $message = $negative_message;
      $icon_name = 'lock';
    }

    $icon = id(new PHUIIconView())
      ->setSpriteSheet(PHUIIconView::SPRITE_ICONS)
      ->setSpriteIcon($icon_name);

    require_celerity_resource('policy-css');

    $phid = $this->getCurrentApplication()->getPHID();
    $explain_uri = "/policy/explain/{$phid}/{$capability}/";

    $message = phutil_tag(
      'div',
      array(
        'class' => 'policy-capability-explanation',
      ),
      array(
        $icon,
        javelin_tag(
          'a',
          array(
            'href' => $explain_uri,
            'sigil' => 'workflow',
          ),
          $message),
      ));

    return array($can_act, $message);
  }

  public function getDefaultResourceSource() {
    return 'phabricator';
  }


  /**
   * Create a new @{class:AphrontDialogView} with defaults filled in.
   *
   * @return AphrontDialogView New dialog.
   */
  protected function newDialog() {
    $submit_uri = new PhutilURI($this->getRequest()->getRequestURI());
    $submit_uri = $submit_uri->getPath();

    return id(new AphrontDialogView())
      ->setUser($this->getRequest()->getUser())
      ->setSubmitURI($submit_uri);
  }

}
