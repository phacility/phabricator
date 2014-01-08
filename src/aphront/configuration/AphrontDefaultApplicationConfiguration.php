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
        '(?P<library>[^/]+)/'.
        '(?P<hash>[a-f0-9]{8})/'.
        '(?P<path>.+\.(?:css|js|jpg|png|swf|gif))'
          => 'CelerityPhabricatorResourceController',
      ),
    );
  }

  /**
   * @phutil-external-symbol class PhabricatorStartup
   */
  public function buildRequest() {
    $parser = new PhutilQueryStringParser();
    $data   = array();

    // If the request has "multipart/form-data" content, we can't use
    // PhutilQueryStringParser to parse it, and the raw data supposedly is not
    // available anyway (according to the PHP documentation, "php://input" is
    // not available for "multipart/form-data" requests). However, it is
    // available at least some of the time (see T3673), so double check that
    // we aren't trying to parse data we won't be able to parse correctly by
    // examining the Content-Type header.
    $content_type = idx($_SERVER, 'CONTENT_TYPE');
    $is_form_data = preg_match('@^multipart/form-data@i', $content_type);

    $raw_input = PhabricatorStartup::getRawInput();
    if (strlen($raw_input) && !$is_form_data) {
      $data += $parser->parseQueryString($raw_input);
    } else if ($_POST) {
      $data += $_POST;
    }

    $data += $parser->parseQueryString(idx($_SERVER, 'QUERY_STRING', ''));

    $request = new AphrontRequest($this->getHost(), $this->getPath());
    $request->setRequestData($data);
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
      // Log these; they don't get shown on the client and can be difficult
      // to debug.
      phlog($ex);

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
        $login_controller = new PhabricatorAuthStartController($request);

        $auth_app_class = 'PhabricatorApplicationAuth';
        $auth_app = PhabricatorApplication::getByClass($auth_app_class);
        $login_controller->setCurrentApplication($auth_app);

        return $login_controller->processRequest();
      }

      $list = $ex->getMoreInfo();
      foreach ($list as $key => $item) {
        $list[$key] = phutil_tag('li', array(), $item);
      }
      if ($list) {
        $list = phutil_tag('ul', array(), $list);
      }

      $content = array(
        phutil_tag(
          'div',
          array(
            'class' => 'aphront-policy-rejection',
          ),
          $ex->getRejection()),
        phutil_tag(
          'div',
          array(
            'class' => 'aphront-capability-details',
          ),
          pht('Users with the "%s" capability:', $ex->getCapabilityName())),
        $list,
      );

      $dialog = new AphrontDialogView();
      $dialog
        ->setTitle($ex->getTitle())
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
      $response->setHTTPResponseCode(500);

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
      $trace = id(new AphrontStackTraceView())
        ->setUser($user)
        ->setTrace($ex->getTrace());
    } else {
      $trace = null;
    }

    $content = phutil_tag(
      'div',
      array('class' => 'aphront-unhandled-exception'),
      array(
        phutil_tag('div', array('class' => 'exception-message'), $message),
        $trace,
      ));

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
    $response->setHTTPResponseCode(500);

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

}
