<?php

final class PhabricatorAuthInviteController
  extends PhabricatorAuthController {

  public function shouldRequireLogin() {
    return false;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $engine = id(new PhabricatorAuthInviteEngine())
      ->setViewer($viewer);

    if ($request->isFormPost()) {
      $engine->setUserHasConfirmedVerify(true);
    }

    $invite_code = $request->getURIData('code');

    try {
      $invite = $engine->processInviteCode($invite_code);
    } catch (PhabricatorAuthInviteDialogException $ex) {
      $response = $this->newDialog()
        ->setTitle($ex->getTitle())
        ->appendParagraph($ex->getBody());

      $submit_text = $ex->getSubmitButtonText();
      if ($submit_text) {
        $response->addSubmitButton($submit_text);
      }

      $submit_uri = $ex->getSubmitButtonURI();
      if ($submit_uri) {
        $response->setSubmitURI($submit_uri);
      }

      $cancel_uri = $ex->getCancelButtonURI();
      $cancel_text = $ex->getCancelButtonText();
      if ($cancel_uri && $cancel_text) {
        $response->addCancelButton($cancel_uri, $cancel_text);
      } else if ($cancel_uri) {
        $response->addCancelButton($cancel_uri);
      }

      return $response;
    } catch (PhabricatorAuthInviteRegisteredException $ex) {
      // We're all set on processing this invite, just send the user home.
      return id(new AphrontRedirectResponse())->setURI('/');
    }

    // Give the user a cookie with the invite code and send them through
    // normal registration. We'll adjust the flow there.
    $request->setCookie(
      PhabricatorCookies::COOKIE_INVITE,
      $invite_code);

    return id(new AphrontRedirectResponse())->setURI('/auth/start/');
  }


}
