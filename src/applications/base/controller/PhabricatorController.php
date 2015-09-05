<?php

abstract class PhabricatorController extends AphrontController {

  private $handles;
  private $extraQuicksandConfig = array();

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

  public function shouldAllowPartialSessions() {
    return false;
  }

  public function shouldRequireEmailVerification() {
    return PhabricatorUserEmail::isEmailVerificationRequired();
  }

  public function shouldAllowRestrictedParameter($parameter_name) {
    return false;
  }

  public function shouldRequireMultiFactorEnrollment() {
    if (!$this->shouldRequireLogin()) {
      return false;
    }

    if (!$this->shouldRequireEnabledUser()) {
      return false;
    }

    if ($this->shouldAllowPartialSessions()) {
      return false;
    }

    $user = $this->getRequest()->getUser();
    if (!$user->getIsStandardUser()) {
      return false;
    }

    return PhabricatorEnv::getEnvConfig('security.require-multi-factor-auth');
  }

  public function shouldAllowLegallyNonCompliantUsers() {
    return false;
  }

  public function isGlobalDragAndDropUploadEnabled() {
    return false;
  }

  public function addExtraQuicksandConfig($config) {
    $this->extraQuicksandConfig += $config;
    return $this;
  }

  private function getExtraQuicksandConfig() {
    return $this->extraQuicksandConfig;
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
          null,
          $partial = false);

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

    PhabricatorEnv::setLocaleCode($user->getTranslation());

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
        $controller = new PhabricatorAuthNeedsApprovalController();
        return $this->delegateToController($controller);
      }
      if ($user->getIsDisabled()) {
        $controller = new PhabricatorDisabledUserController();
        return $this->delegateToController($controller);
      }
    }

    $auth_class = 'PhabricatorAuthApplication';
    $auth_application = PhabricatorApplication::getByClass($auth_class);

    // Require partial sessions to finish login before doing anything.
    if (!$this->shouldAllowPartialSessions()) {
      if ($user->hasSession() &&
          $user->getSession()->getIsPartial()) {
        $login_controller = new PhabricatorAuthFinishController();
        $this->setCurrentApplication($auth_application);
        return $this->delegateToController($login_controller);
      }
    }

    // Check if the user needs to configure MFA.
    $need_mfa = $this->shouldRequireMultiFactorEnrollment();
    $have_mfa = $user->getIsEnrolledInMultiFactor();
    if ($need_mfa && !$have_mfa) {
      // Check if the cache is just out of date. Otherwise, roadblock the user
      // and require MFA enrollment.
      $user->updateMultiFactorEnrollment();
      if (!$user->getIsEnrolledInMultiFactor()) {
        $mfa_controller = new PhabricatorAuthNeedsMultiFactorController();
        $this->setCurrentApplication($auth_application);
        return $this->delegateToController($mfa_controller);
      }
    }

    if ($this->shouldRequireLogin()) {
      // This actually means we need either:
      //   - a valid user, or a public controller; and
      //   - permission to see the application; and
      //   - permission to see at least one Space if spaces are configured.

      $allow_public = $this->shouldAllowPublic() &&
                      PhabricatorEnv::getEnvConfig('policy.allow-public');

      // If this controller isn't public, and the user isn't logged in, require
      // login.
      if (!$allow_public && !$user->isLoggedIn()) {
        $login_controller = new PhabricatorAuthStartController();
        $this->setCurrentApplication($auth_application);
        return $this->delegateToController($login_controller);
      }

      if ($user->isLoggedIn()) {
        if ($this->shouldRequireEmailVerification()) {
          if (!$user->getIsEmailVerified()) {
            $controller = new PhabricatorMustVerifyEmailController();
            $this->setCurrentApplication($auth_application);
            return $this->delegateToController($controller);
          }
        }
      }

      // If Spaces are configured, require that the user have access to at
      // least one. If we don't do this, they'll get confusing error messages
      // later on.
      $spaces = PhabricatorSpacesNamespaceQuery::getSpacesExist();
      if ($spaces) {
        $viewer_spaces = PhabricatorSpacesNamespaceQuery::getViewerSpaces(
          $user);
        if (!$viewer_spaces) {
          $controller = new PhabricatorSpacesNoAccessController();
          return $this->delegateToController($controller);
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


    if (!$this->shouldAllowLegallyNonCompliantUsers()) {
      $legalpad_class = 'PhabricatorLegalpadApplication';
      $legalpad = id(new PhabricatorApplicationQuery())
        ->setViewer($user)
        ->withClasses(array($legalpad_class))
        ->withInstalled(true)
        ->execute();
      $legalpad = head($legalpad);

      $doc_query = id(new LegalpadDocumentQuery())
        ->setViewer($user)
        ->withSignatureRequired(1)
        ->needViewerSignatures(true);

      if ($user->hasSession() &&
          !$user->getSession()->getIsPartial() &&
          !$user->getSession()->getSignedLegalpadDocuments() &&
          $user->isLoggedIn() &&
          $legalpad) {

        $sign_docs = $doc_query->execute();
        $must_sign_docs = array();
        foreach ($sign_docs as $sign_doc) {
          if (!$sign_doc->getUserSignature($user->getPHID())) {
            $must_sign_docs[] = $sign_doc;
          }
        }
        if ($must_sign_docs) {
          $controller = new LegalpadDocumentSignController();
          $this->getRequest()->setURIMap(array(
            'id' => head($must_sign_docs)->getID(),
          ));
          $this->setCurrentApplication($legalpad);
          return $this->delegateToController($controller);
        } else {
          $engine = id(new PhabricatorAuthSessionEngine())
            ->signLegalpadDocuments($user, $sign_docs);
        }
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
    return $this->buildPageResponse($page);
  }

  private function buildPageResponse($page) {
    if ($this->getRequest()->isQuicksand()) {
      $response = id(new AphrontAjaxResponse())
        ->setContent($page->renderForQuicksand(
          $this->getExtraQuicksandConfig()));
    } else {
      $response = id(new AphrontWebpageResponse())
        ->setContent($page->render());
    }

    return $response;
  }

  public function getApplicationURI($path = '') {
    if (!$this->getCurrentApplication()) {
      throw new Exception(pht('No application!'));
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

    if (idx($options, 'device', true)) {
      $page->setDeviceReady(true);
    }

    $page->setShowFooter(idx($options, 'showFooter', true));
    $page->setShowChrome(idx($options, 'chrome', true));

    $application_menu = $this->buildApplicationMenu();
    if ($application_menu) {
      $page->setApplicationMenu($application_menu);
    }

    return $this->buildPageResponse($page);
  }

  public function willSendResponse(AphrontResponse $response) {
    $request = $this->getRequest();

    if ($response instanceof AphrontDialogResponse) {
      if (!$request->isAjax() && !$request->isQuicksand()) {
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
          ->setDeviceReady(true)
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
      if ($request->isAjax() || $request->isQuicksand()) {
        return id(new AphrontAjaxResponse())
          ->setContent(
            array(
              'redirect' => $response->getURI(),
            ));
      }
    }

    return $response;
  }

  /**
   * WARNING: Do not call this in new code.
   *
   * @deprecated See "Handles Technical Documentation".
   */
  protected function loadViewerHandles(array $phids) {
    return id(new PhabricatorHandleQuery())
      ->setViewer($this->getRequest()->getUser())
      ->withPHIDs($phids)
      ->execute();
  }

  public function buildApplicationMenu() {
    return null;
  }

  protected function buildApplicationCrumbs() {
    $crumbs = array();

    $application = $this->getCurrentApplication();
    if ($application) {
      $icon = $application->getFontIcon();
      if (!$icon) {
        $icon = 'fa-puzzle';
      }

      $crumbs[] = id(new PHUICrumbView())
        ->setHref($this->getApplicationURI())
        ->setName($application->getName())
        ->setIcon($icon);
    }

    $view = new PHUICrumbsView();
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
      $icon_name = 'fa-play-circle-o lightgreytext';
    } else {
      $message = $negative_message;
      $icon_name = 'fa-lock';
    }

    $icon = id(new PHUIIconView())
      ->setIconFont($icon_name);

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
  public function newDialog() {
    $submit_uri = new PhutilURI($this->getRequest()->getRequestURI());
    $submit_uri = $submit_uri->getPath();

    return id(new AphrontDialogView())
      ->setUser($this->getRequest()->getUser())
      ->setSubmitURI($submit_uri);
  }

  protected function buildTransactionTimeline(
    PhabricatorApplicationTransactionInterface $object,
    PhabricatorApplicationTransactionQuery $query,
    PhabricatorMarkupEngine $engine = null,
    $render_data = array()) {

    $viewer = $this->getRequest()->getUser();
    $xaction = $object->getApplicationTransactionTemplate();
    $view = $xaction->getApplicationTransactionViewObject();

    $pager = id(new AphrontCursorPagerView())
      ->readFromRequest($this->getRequest())
      ->setURI(new PhutilURI(
        '/transactions/showolder/'.$object->getPHID().'/'));

    $xactions = $query
      ->setViewer($viewer)
      ->withObjectPHIDs(array($object->getPHID()))
      ->needComments(true)
      ->executeWithCursorPager($pager);
    $xactions = array_reverse($xactions);

    if ($engine) {
      foreach ($xactions as $xaction) {
        if ($xaction->getComment()) {
          $engine->addObject(
            $xaction->getComment(),
            PhabricatorApplicationTransactionComment::MARKUP_FIELD_COMMENT);
        }
      }
      $engine->process();
      $view->setMarkupEngine($engine);
    }

    $timeline = $view
      ->setUser($viewer)
      ->setObjectPHID($object->getPHID())
      ->setTransactions($xactions)
      ->setPager($pager)
      ->setRenderData($render_data)
      ->setQuoteTargetID($this->getRequest()->getStr('quoteTargetID'))
      ->setQuoteRef($this->getRequest()->getStr('quoteRef'));
    $object->willRenderTimeline($timeline, $this->getRequest());

    return $timeline;
  }

}
