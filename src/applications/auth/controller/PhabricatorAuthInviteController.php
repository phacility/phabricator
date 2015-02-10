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

    try {
      $invite = $engine->processInviteCode($request->getURIData('code'));
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


    // TODO: This invite is good, but we need to drive the user through
    // registration.
    throw new Exception(pht('TODO: Build invite/registration workflow.'));
  }


}
