<?php

final class PhabricatorPeopleWelcomeController
  extends PhabricatorPeopleController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $admin = $request->getUser();

    $user = id(new PhabricatorPeopleQuery())
      ->setViewer($admin)
      ->withIDs(array($this->id))
      ->executeOne();
    if (!$user) {
      return new Aphront404Response();
    }

    $profile_uri = '/p/'.$user->getUsername().'/';

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
