<?php

/**
 * @group aphront
 */
class AphrontDefaultApplicationConfiguration
  extends AphrontApplicationConfiguration {

  public function __construct() {

  }

  public function getApplicationName() {
    return 'aphront-default';
  }

  public function getURIMap() {
    return $this->getResourceURIMapRules() + array(
      '/(?:(?P<filter>(?:jump))/)?' =>
        'PhabricatorDirectoryMainController',

      '/typeahead/' => array(
        'common/(?P<type>\w+)/'
          => 'PhabricatorTypeaheadCommonDatasourceController',
      ),

      '/login/' => array(
        '' => 'PhabricatorLoginController',
        'email/' => 'PhabricatorEmailLoginController',
        'etoken/(?P<token>\w+)/' => 'PhabricatorEmailTokenController',
        'refresh/' => 'PhabricatorRefreshCSRFController',
        'validate/' => 'PhabricatorLoginValidateController',
        'mustverify/' => 'PhabricatorMustVerifyEmailController',
      ),

      '/logout/' => 'PhabricatorLogoutController',

      '/oauth/' => array(
        '(?P<provider>\w+)/' => array(
          'login/'     => 'PhabricatorOAuthLoginController',
          'diagnose/'  => 'PhabricatorOAuthDiagnosticsController',
          'unlink/'    => 'PhabricatorOAuthUnlinkController',
        ),
      ),

      '/ldap/' => array(
        'login/' => 'PhabricatorLDAPLoginController',
        'unlink/'    => 'PhabricatorLDAPUnlinkController',
      ),

      '/oauthserver/' => array(
        'auth/'          => 'PhabricatorOAuthServerAuthController',
        'test/'          => 'PhabricatorOAuthServerTestController',
        'token/'         => 'PhabricatorOAuthServerTokenController',
        'clientauthorization/' => array(
          '' => 'PhabricatorOAuthClientAuthorizationListController',
          'delete/(?P<phid>[^/]+)/' =>
            'PhabricatorOAuthClientAuthorizationDeleteController',
          'edit/(?P<phid>[^/]+)/' =>
            'PhabricatorOAuthClientAuthorizationEditController',
        ),
        'client/' => array(
          ''                        => 'PhabricatorOAuthClientListController',
          'create/'                 => 'PhabricatorOAuthClientEditController',
          'delete/(?P<phid>[^/]+)/' => 'PhabricatorOAuthClientDeleteController',
          'edit/(?P<phid>[^/]+)/'   => 'PhabricatorOAuthClientEditController',
          'view/(?P<phid>[^/]+)/'   => 'PhabricatorOAuthClientViewController',
        ),
      ),

      '/~/' => array(
        '' => 'DarkConsoleController',
        'data/(?P<key>[^/]+)/' => 'DarkConsoleDataController',
      ),

      '/status/' => 'PhabricatorStatusController',


      '/help/' => array(
        'keyboardshortcut/' => 'PhabricatorHelpKeyboardShortcutController',
      ),

      '/notification/' => array(
        '(?:(?P<filter>all|unread)/)?'
          => 'PhabricatorNotificationListController',
        'panel/' => 'PhabricatorNotificationPanelController',
        'individual/' => 'PhabricatorNotificationIndividualController',
        'status/' => 'PhabricatorNotificationStatusController',
        'clear/' => 'PhabricatorNotificationClearController',
      ),

      '/debug/' => 'PhabricatorDebugController',
    );
  }

  protected function getResourceURIMapRules() {
    return array(
      '/res/' => array(
        '(?:(?P<mtime>[0-9]+)T/)?'.
        '(?P<package>pkg/)?'.
        '(?P<hash>[a-f0-9]{8})/'.
        '(?P<path>.+\.(?:css|js|jpg|png|swf|gif))'
          => 'CelerityPhabricatorResourceController',
      ),
    );
  }

  public function buildRequest() {
    $request = new AphrontRequest($this->getHost(), $this->getPath());
    $request->setRequestData($_GET + $_POST);
    $request->setApplicationConfiguration($this);
    return $request;
  }

  public function handleException(Exception $ex) {
    $request = $this->getRequest();

    // For Conduit requests, return a Conduit response.
    if ($request->isConduit()) {
      $response = new ConduitAPIResponse();
      $response->setErrorCode(get_class($ex));
      $response->setErrorInfo($ex->getMessage());

      return id(new AphrontJSONResponse())
        ->setAddJSONShield(false)
        ->setContent($response->toDictionary());
    }

    // For non-workflow requests, return a Ajax response.
    if ($request->isAjax() && !$request->isJavelinWorkflow()) {
      $response = new AphrontAjaxResponse();
      $response->setError(
        array(
          'code' => get_class($ex),
          'info' => $ex->getMessage(),
        ));
      return $response;
    }

    $is_serious = PhabricatorEnv::getEnvConfig('phabricator.serious-business');

    $user = $request->getUser();
    if (!$user) {
      // If we hit an exception very early, we won't have a user.
      $user = new PhabricatorUser();
    }

    if ($ex instanceof PhabricatorPolicyException) {

      if (!$user->isLoggedIn()) {
        // If the user isn't logged in, just give them a login form. This is
        // probably a generally more useful response than a policy dialog that
        // they have to click through to get a login form.
        //
        // Possibly we should add a header here like "you need to login to see
        // the thing you are trying to look at".
        $login_controller = new PhabricatorLoginController($request);
        return $login_controller->processRequest();
      }

      $content = hsprintf(
        '<div class="aphront-policy-exception">%s</div>',
        $ex->getMessage());

      $dialog = new AphrontDialogView();
      $dialog
        ->setTitle(
            $is_serious
              ? 'Access Denied'
              : "You Shall Not Pass")
        ->setClass('aphront-access-dialog')
        ->setUser($user)
        ->appendChild($content);

      if ($this->getRequest()->isAjax()) {
        $dialog->addCancelButton('/', 'Close');
      } else {
        $dialog->addCancelButton('/', $is_serious ? 'OK' : 'Away With Thee');
      }

      $response = new AphrontDialogResponse();
      $response->setDialog($dialog);
      return $response;
    }

    if ($ex instanceof AphrontUsageException) {
      $error = new AphrontErrorView();
      $error->setTitle($ex->getTitle());
      $error->appendChild($ex->getMessage());

      $view = new PhabricatorStandardPageView();
      $view->setRequest($this->getRequest());
      $view->appendChild($error);

      $response = new AphrontWebpageResponse();
      $response->setContent($view->render());

      return $response;
    }


    // Always log the unhandled exception.
    phlog($ex);

    $class    = get_class($ex);
    $message  = $ex->getMessage();

    if ($ex instanceof AphrontQuerySchemaException) {
      $message .=
        "\n\n".
        "NOTE: This usually indicates that the MySQL schema has not been ".
        "properly upgraded. Run 'bin/storage upgrade' to ensure your ".
        "schema is up to date.";
    }

    if (PhabricatorEnv::getEnvConfig('phabricator.developer-mode')) {
      $trace = $this->renderStackTrace($ex->getTrace(), $user);
    } else {
      $trace = null;
    }

    $content = hsprintf(
      '<div class="aphront-unhandled-exception">'.
        '<div class="exception-message">%s</div>'.
        '%s'.
      '</div>',
      $message,
      $trace);

    $dialog = new AphrontDialogView();
    $dialog
      ->setTitle('Unhandled Exception ("'.$class.'")')
      ->setClass('aphront-exception-dialog')
      ->setUser($user)
      ->appendChild($content);

    if ($this->getRequest()->isAjax()) {
      $dialog->addCancelButton('/', 'Close');
    }

    $response = new AphrontDialogResponse();
    $response->setDialog($dialog);

    return $response;
  }

  public function willSendResponse(AphrontResponse $response) {
    return $response;
  }

  public function build404Controller() {
    return array(new Phabricator404Controller($this->getRequest()), array());
  }

  public function buildRedirectController($uri) {
    return array(
      new PhabricatorRedirectController($this->getRequest()),
      array(
        'uri' => $uri,
      ));
  }

  private function renderStackTrace($trace, PhabricatorUser $user) {

    $libraries = PhutilBootloader::getInstance()->getAllLibraries();

    // TODO: Make this configurable?
    $path = 'https://secure.phabricator.com/diffusion/%s/browse/master/src/';

    $callsigns = array(
      'arcanist' => 'ARC',
      'phutil' => 'PHU',
      'phabricator' => 'P',
    );

    $rows = array();
    $depth = count($trace);
    foreach ($trace as $part) {
      $lib = null;
      $file = idx($part, 'file');
      $relative = $file;
      foreach ($libraries as $library) {
        $root = phutil_get_library_root($library);
        if (Filesystem::isDescendant($file, $root)) {
          $lib = $library;
          $relative = Filesystem::readablePath($file, $root);
          break;
        }
      }

      $where = '';
      if (isset($part['class'])) {
        $where .= $part['class'].'::';
      }
      if (isset($part['function'])) {
        $where .= $part['function'].'()';
      }

      if ($file) {
        if (isset($callsigns[$lib])) {
          $attrs = array('title' => $file);
          try {
            $attrs['href'] = $user->loadEditorLink(
              '/src/'.$relative,
              $part['line'],
              $callsigns[$lib]);
          } catch (Exception $ex) {
            // The database can be inaccessible.
          }
          if (empty($attrs['href'])) {
            $attrs['href'] = sprintf($path, $callsigns[$lib]).
              str_replace(DIRECTORY_SEPARATOR, '/', $relative).
              '$'.$part['line'];
            $attrs['target'] = '_blank';
          }
          $file_name = phutil_tag(
            'a',
            $attrs,
            $relative);
        } else {
          $file_name = phutil_tag(
            'span',
            array(
              'title' => $file,
            ),
            $relative);
        }
        $file_name = hsprintf('%s : %d', $file_name, $part['line']);
      } else {
        $file_name = phutil_tag('em', array(), '(Internal)');
      }


      $rows[] = array(
        $depth--,
        $lib,
        $file_name,
        $where,
      );
    }
    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'Depth',
        'Library',
        'File',
        'Where',
      ));
    $table->setColumnClasses(
      array(
        'n',
        '',
        '',
        'wide',
      ));

    return hsprintf(
      '<div class="exception-trace">'.
        '<div class="exception-trace-header">Stack Trace</div>'.
        '%s'.
      '</div>',
      $table->render());
  }

}
