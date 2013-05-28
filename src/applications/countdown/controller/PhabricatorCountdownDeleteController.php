<?php

/**
 * @group countdown
 */
final class PhabricatorCountdownDeleteController
  extends PhabricatorCountdownController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $countdown = id(new CountdownQuery())
      ->setViewer($user)
      ->withIDs(array($this->id))
      ->executeOne();

    if (!$countdown) {
      return new Aphront404Response();
    }

    if (($countdown->getAuthorPHID() !== $user->getPHID())
        && $user->getIsAdmin() === false) {
      return new Aphront403Response();
    }

    if ($request->isFormPost()) {
      $countdown->delete();
      return id(new AphrontRedirectResponse())
        ->setURI('/countdown/');
    }

    $inst = pht('Are you sure you want to delete the countdown %s?',
            $countdown->getTitle());

    $dialog = new AphrontDialogView();
    $dialog->setUser($request->getUser());
    $dialog->setTitle(pht('Really delete this countdown?'));
    $dialog->appendChild(hsprintf('<p>%s</p>', $inst));
    $dialog->addSubmitButton(pht('Delete'));
    $dialog->addCancelButton('/countdown/');
    $dialog->setSubmitURI($request->getPath());

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
