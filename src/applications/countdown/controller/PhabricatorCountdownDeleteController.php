<?php

final class PhabricatorCountdownDeleteController
  extends PhabricatorCountdownController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $timer = id(new PhabricatorTimer())->load($this->id);
    if (!$timer) {
      return new Aphront404Response();
    }

    if (($timer->getAuthorPHID() !== $user->getPHID())
        && $user->getIsAdmin() === false) {
      return new Aphront403Response();
    }

    if ($request->isFormPost()) {
      $timer->delete();
      return id(new AphrontRedirectResponse())
        ->setURI('/countdown/');
    }

    $dialog = new AphrontDialogView();
    $dialog->setUser($request->getUser());
    $dialog->setTitle('Really delete this countdown?');
    $dialog->appendChild(
      '<p>Are you sure you want to delete the countdown "'.
      phutil_escape_html($timer->getTitle()).'"?</p>');
    $dialog->addSubmitButton('Delete');
    $dialog->addCancelButton('/countdown/');
    $dialog->setSubmitURI($request->getPath());

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
