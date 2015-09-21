<?php

final class PhabricatorPeopleWelcomeController
  extends PhabricatorPeopleController {

  public function handleRequest(AphrontRequest $request) {
    $admin = $this->getViewer();

    $user = id(new PhabricatorPeopleQuery())
      ->setViewer($admin)
      ->withIDs(array($request->getURIData('id')))
      ->executeOne();
    if (!$user) {
      return new Aphront404Response();
    }

    $profile_uri = '/p/'.$user->getUsername().'/';

    if (!$user->canEstablishWebSessions()) {
      return $this->newDialog()
        ->setTitle(pht('Not a Normal User'))
        ->appendParagraph(
          pht(
            'You can not send this user a welcome mail because they are not '.
            'a normal user and can not log in to the web interface. Special '.
            'users (like bots and mailing lists) are unable to establish web '.
            'sessions.'))
        ->addCancelButton($profile_uri, pht('Done'));
    }

    if ($request->isFormPost()) {
      $user->sendWelcomeEmail($admin);
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
