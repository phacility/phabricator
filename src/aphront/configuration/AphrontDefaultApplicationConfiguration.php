<?php

/**
 * NOTE: Do not extend this!
 *
 * @concrete-extensible
 */
class AphrontDefaultApplicationConfiguration
  extends AphrontApplicationConfiguration {

  public function __construct() {}

  public function getApplicationName() {
    return 'aphront-default';
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

    $cookie_prefix = PhabricatorEnv::getEnvConfig('phabricator.cookie-prefix');

    $request = new AphrontRequest($this->getHost(), $this->getPath());
    $request->setRequestData($data);
    $request->setApplicationConfiguration($this);
    $request->setCookiePrefix($cookie_prefix);

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
    if ($request->isAjax() && !$request->isWorkflow()) {
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

    $user = $request->getUser();
    if (!$user) {
      // If we hit an exception very early, we won't have a user.
      $user = new PhabricatorUser();
    }

    if ($ex instanceof PhabricatorSystemActionRateLimitException) {
      $dialog = id(new AphrontDialogView())
        ->setTitle(pht('Slow Down!'))
        ->setUser($user)
        ->setErrors(array(pht('You are being rate limited.')))
        ->appendParagraph($ex->getMessage())
        ->appendParagraph($ex->getRateExplanation())
        ->addCancelButton('/', pht('Okaaaaaaaaaaaaaay...'));

      $response = new AphrontDialogResponse();
      $response->setDialog($dialog);
      return $response;
    }

    if ($ex instanceof PhabricatorAuthHighSecurityRequiredException) {

      $form = id(new PhabricatorAuthSessionEngine())->renderHighSecurityForm(
        $ex->getFactors(),
        $ex->getFactorValidationResults(),
        $user,
        $request);

      $dialog = id(new AphrontDialogView())
        ->setUser($user)
        ->setTitle(pht('Entering High Security'))
        ->setShortTitle(pht('Security Checkpoint'))
        ->setWidth(AphrontDialogView::WIDTH_FORM)
        ->addHiddenInput(AphrontRequest::TYPE_HISEC, true)
        ->setErrors(
          array(
            pht(
              'You are taking an action which requires you to enter '.
              'high security.'),
          ))
        ->appendParagraph(
          pht(
            'High security mode helps protect your account from security '.
            'threats, like session theft or someone messing with your stuff '.
            'while you\'re grabbing a coffee. To enter high security mode, '.
            'confirm your credentials.'))
        ->appendChild($form->buildLayoutView())
        ->appendParagraph(
          pht(
            'Your account will remain in high security mode for a short '.
            'period of time. When you are finished taking sensitive '.
            'actions, you should leave high security.'))
        ->setSubmitURI($request->getPath())
        ->addCancelButton($ex->getCancelURI())
        ->addSubmitButton(pht('Enter High Security'));

      $request_parameters = $request->getPassthroughRequestParameters(
        $respect_quicksand = true);
      foreach ($request_parameters as $key => $value) {
        $dialog->addHiddenInput($key, $value);
      }

      $response = new AphrontDialogResponse();
      $response->setDialog($dialog);
      return $response;
    }

    if ($ex instanceof PhabricatorPolicyException) {
      if (!$user->isLoggedIn()) {
        // If the user isn't logged in, just give them a login form. This is
        // probably a generally more useful response than a policy dialog that
        // they have to click through to get a login form.
        //
        // Possibly we should add a header here like "you need to login to see
        // the thing you are trying to look at".
        $login_controller = new PhabricatorAuthStartController();
        $login_controller->setRequest($request);

        $auth_app_class = 'PhabricatorAuthApplication';
        $auth_app = PhabricatorApplication::getByClass($auth_app_class);
        $login_controller->setCurrentApplication($auth_app);

        return $login_controller->handleRequest($request);
      }

      $content = array(
        phutil_tag(
          'div',
          array(
            'class' => 'aphront-policy-rejection',
          ),
          $ex->getRejection()),
      );

      if ($ex->getCapabilityName()) {
        $list = $ex->getMoreInfo();
        foreach ($list as $key => $item) {
          $list[$key] = phutil_tag('li', array(), $item);
        }
        if ($list) {
          $list = phutil_tag('ul', array(), $list);
        }

        $content[] = phutil_tag(
          'div',
          array(
            'class' => 'aphront-capability-details',
          ),
          pht('Users with the "%s" capability:', $ex->getCapabilityName()));

        $content[] = $list;
      }

      $dialog = id(new AphrontDialogView())
        ->setTitle($ex->getTitle())
        ->setClass('aphront-access-dialog')
        ->setUser($user)
        ->appendChild($content);

      if ($this->getRequest()->isAjax()) {
        $dialog->addCancelButton('/', pht('Close'));
      } else {
        $dialog->addCancelButton('/', pht('OK'));
      }

      $response = new AphrontDialogResponse();
      $response->setDialog($dialog);
      return $response;
    }

    if ($ex instanceof AphrontUsageException) {
      $error = new PHUIInfoView();
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

    if ($ex instanceof AphrontSchemaQueryException) {
      $message .= "\n\n".pht(
        "NOTE: This usually indicates that the MySQL schema has not been ".
        "properly upgraded. Run '%s' to ensure your schema is up to date.",
        'bin/storage upgrade');
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
      ->setTitle(pht('Unhandled Exception ("%s")', $class))
      ->setClass('aphront-exception-dialog')
      ->setUser($user)
      ->appendChild($content);

    if ($this->getRequest()->isAjax()) {
      $dialog->addCancelButton('/', pht('Close'));
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
    return array(new Phabricator404Controller(), array());
  }

  public function buildRedirectController($uri, $external) {
    return array(
      new PhabricatorRedirectController(),
      array(
        'uri' => $uri,
        'external' => $external,
      ),
    );
  }

}
