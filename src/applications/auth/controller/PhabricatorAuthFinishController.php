<?php

final class PhabricatorAuthFinishController
  extends PhabricatorAuthController {

  public function shouldRequireLogin() {
    return false;
  }

  public function shouldAllowPartialSessions() {
    return true;
  }

  public function shouldAllowLegallyNonCompliantUsers() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    // If the user already has a full session, just kick them out of here.
    $has_partial_session = $viewer->hasSession() &&
                           $viewer->getSession()->getIsPartial();
    if (!$has_partial_session) {
      return id(new AphrontRedirectResponse())->setURI('/');
    }

    $engine = new PhabricatorAuthSessionEngine();

    // If this cookie is set, the user is headed into a high security area
    // after login (normally because of a password reset) so if they are
    // able to pass the checkpoint we just want to put their account directly
    // into high security mode, rather than prompt them again for the same
    // set of credentials.
    $jump_into_hisec = $request->getCookie(PhabricatorCookies::COOKIE_HISEC);

    try {
      $token = $engine->requireHighSecuritySession(
        $viewer,
        $request,
        '/logout/',
        $jump_into_hisec);
    } catch (PhabricatorAuthHighSecurityRequiredException $ex) {
      $form = id(new PhabricatorAuthSessionEngine())->renderHighSecurityForm(
        $ex->getFactors(),
        $ex->getFactorValidationResults(),
        $viewer,
        $request);

      return $this->newDialog()
        ->setTitle(pht('Provide Multi-Factor Credentials'))
        ->setShortTitle(pht('Multi-Factor Login'))
        ->setWidth(AphrontDialogView::WIDTH_FORM)
        ->addHiddenInput(AphrontRequest::TYPE_HISEC, true)
        ->appendParagraph(
          pht(
            'Welcome, %s. To complete the login process, provide your '.
            'multi-factor credentials.',
            phutil_tag('strong', array(), $viewer->getUsername())))
        ->appendChild($form->buildLayoutView())
        ->setSubmitURI($request->getPath())
        ->addCancelButton($ex->getCancelURI())
        ->addSubmitButton(pht('Continue'));
    }

    // Upgrade the partial session to a full session.
    $engine->upgradePartialSession($viewer);

    // TODO: It might be nice to add options like "bind this session to my IP"
    // here, even for accounts without multi-factor auth attached to them.

    $next = PhabricatorCookies::getNextURICookie($request);
    $request->clearCookie(PhabricatorCookies::COOKIE_NEXTURI);
    $request->clearCookie(PhabricatorCookies::COOKIE_HISEC);

    if (!PhabricatorEnv::isValidLocalURIForLink($next)) {
      $next = '/';
    }

    return id(new AphrontRedirectResponse())->setURI($next);
  }

}
