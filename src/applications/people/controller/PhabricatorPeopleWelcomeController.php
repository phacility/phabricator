<?php

final class PhabricatorPeopleWelcomeController
  extends PhabricatorPeopleController {

  public function shouldRequireAdmin() {
    // You need to be an administrator to actually send welcome email, but
    // we let anyone hit this page so they can get a nice error dialog
    // explaining the issue.
    return false;
  }

  public function handleRequest(AphrontRequest $request) {
    $admin = $this->getViewer();

    $user = id(new PhabricatorPeopleQuery())
      ->setViewer($admin)
      ->withIDs(array($request->getURIData('id')))
      ->executeOne();
    if (!$user) {
      return new Aphront404Response();
    }

    $id = $user->getID();
    $profile_uri = "/people/manage/{$id}/";

    $welcome_engine = id(new PhabricatorPeopleWelcomeMailEngine())
      ->setSender($admin)
      ->setRecipient($user);

    try {
      $welcome_engine->validateMail();
    } catch (PhabricatorPeopleMailEngineException $ex) {
      return $this->newDialog()
        ->setTitle($ex->getTitle())
        ->appendParagraph($ex->getBody())
        ->addCancelButton($profile_uri, pht('Done'));
    }

    if ($request->isFormPost()) {
      $welcome_engine->sendMail();
      return id(new AphrontRedirectResponse())->setURI($profile_uri);
    }

    return $this->newDialog()
      ->setTitle(pht('Send Welcome Email'))
      ->appendParagraph(
        pht(
          'This will send the user another copy of the "Welcome to '.
          'Phabricator" email that users normally receive when their '.
          'accounts are created.'))
      ->appendParagraph(
        pht(
          'The email contains a link to log in to their account. Sending '.
          'another copy of the email can be useful if the original was lost '.
          'or never sent.'))
      ->appendParagraph(pht('The email will identify you as the sender.'))
      ->addSubmitButton(pht('Send Email'))
      ->addCancelButton($profile_uri);
  }

}
