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
        $user->attachAlternateCSRFString(PhabricatorHash::weakDigest($phsid));
      }

      $request->setUser($user);
    }

    id(new PhabricatorAuthSessionEngine())
      ->willServeRequestForUser($user);

    if (PhabricatorEnv::getEnvConfig('darkconsole.enabled')) {
      $dark_console = PhabricatorDarkConsoleSetting::SETTINGKEY;
      if ($user->getUserSetting($dark_console) ||
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

    // Require users sign Legalpad documents before we check if they have
    // MFA. If we don't do this, they can get stuck in a state where they
    // can't add MFA until they sign, and can't sign until they add MFA.
    // See T13024 and PHI223.
    $result = $this->requireLegalpadSignatures();
    if ($result !== null) {
      return $result;
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

      // If users need approval, require they wait here. We do this near the
      // end so they can take other actions (like verifying email, signing
      // documents, and enrolling in MFA) while waiting for an admin to take a
      // look at things. See T13024 for more discussion.
      if ($this->shouldRequireEnabledUser()) {
        if ($user->isLoggedIn() && !$user->getIsApproved()) {
          $controller = new PhabricatorAuthNeedsApprovalController();
          return $this->delegateToController($controller);
        }
      }
    }

    // NOTE: We do this last so that users get a login page instead of a 403
    // if they need to login.
    if ($this->shouldRequireAdmin() && !$user->getIsAdmin()) {
      return new Aphront403Response();
    }
  }

  public function getApplicationURI($path = '') {
    if (!$this->getCurrentApplication()) {
      throw new Exception(pht('No application!'));
    }
    return $this->getCurrentApplication()->getApplicationURI($path);
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
      $icon = $application->getIcon();
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
      ->setIcon($icon_name);

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

  public function newPage() {
    $page = id(new PhabricatorStandardPageView())
      ->setRequest($this->getRequest())
      ->setController($this)
      ->setDeviceReady(true);

    $application = $this->getCurrentApplication();
    if ($application) {
      $page->setApplicationName($application->getName());
      if ($application->getTitleGlyph()) {
        $page->setGlyph($application->getTitleGlyph());
      }
    }

    $viewer = $this->getRequest()->getUser();
    if ($viewer) {
      $page->setUser($viewer);
    }

    return $page;
  }

  public function newApplicationMenu() {
    return id(new PHUIApplicationMenuView())
      ->setViewer($this->getViewer());
  }

  public function newCurtainView($object = null) {
    $viewer = $this->getViewer();

    $action_id = celerity_generate_unique_node_id();

    $action_list = id(new PhabricatorActionListView())
      ->setViewer($viewer)
      ->setID($action_id);

    // NOTE: Applications (objects of class PhabricatorApplication) can't
    // currently be set here, although they don't need any of the extensions
    // anyway. This should probably work differently than it does, though.
    if ($object) {
      if ($object instanceof PhabricatorLiskDAO) {
        $action_list->setObject($object);
      }
    }

    $curtain = id(new PHUICurtainView())
      ->setViewer($viewer)
      ->setActionList($action_list);

    if ($object) {
      $panels = PHUICurtainExtension::buildExtensionPanels($viewer, $object);
      foreach ($panels as $panel) {
        $curtain->addPanel($panel);
      }
    }

    return $curtain;
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


  public function buildApplicationCrumbsForEditEngine() {
    // TODO: This is kind of gross, I'm basically just making this public so
    // I can use it in EditEngine. We could do this without making it public
    // by using controller delegation, or make it properly public.
    return $this->buildApplicationCrumbs();
  }

  private function requireLegalpadSignatures() {
    if (!$this->shouldRequireLogin()) {
      return null;
    }

    if ($this->shouldAllowLegallyNonCompliantUsers()) {
      return null;
    }

    $viewer = $this->getViewer();

    if (!$viewer->hasSession()) {
      return null;
    }

    $session = $viewer->getSession();
    if ($session->getIsPartial()) {
      // If the user hasn't made it through MFA yet, require they survive
      // MFA first.
      return null;
    }

    if ($session->getSignedLegalpadDocuments()) {
      return null;
    }

    if (!$viewer->isLoggedIn()) {
      return null;
    }

    $must_sign_docs = array();
    $sign_docs = array();

    $legalpad_class = 'PhabricatorLegalpadApplication';
    $legalpad_installed = PhabricatorApplication::isClassInstalledForViewer(
      $legalpad_class,
      $viewer);
    if ($legalpad_installed) {
      $sign_docs = id(new LegalpadDocumentQuery())
        ->setViewer($viewer)
        ->withSignatureRequired(1)
        ->needViewerSignatures(true)
        ->setOrder('oldest')
        ->execute();

      foreach ($sign_docs as $sign_doc) {
        if (!$sign_doc->getUserSignature($viewer->getPHID())) {
          $must_sign_docs[] = $sign_doc;
        }
      }
    }

    if (!$must_sign_docs) {
      // If nothing needs to be signed (either because there are no documents
      // which require a signature, or because the user has already signed
      // all of them) mark the session as good and continue.
      $engine = id(new PhabricatorAuthSessionEngine())
        ->signLegalpadDocuments($viewer, $sign_docs);

      return null;
    }

    $request = $this->getRequest();
    $request->setURIMap(
      array(
        'id' => head($must_sign_docs)->getID(),
      ));

    $application = PhabricatorApplication::getByClass($legalpad_class);
    $this->setCurrentApplication($application);

    $controller = new LegalpadDocumentSignController();
    return $this->delegateToController($controller);
  }


/* -(  Deprecated  )--------------------------------------------------------- */


  /**
   * DEPRECATED. Use @{method:newPage}.
   */
  public function buildStandardPageView() {
    return $this->newPage();
  }


  /**
   * DEPRECATED. Use @{method:newPage}.
   */
  public function buildStandardPageResponse($view, array $data) {
    $page = $this->buildStandardPageView();
    $page->appendChild($view);
    return $page->produceAphrontResponse();
  }

}
