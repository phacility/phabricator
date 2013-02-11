<?php

final class PhabricatorFileDeleteController extends PhabricatorFileController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $file = id(new PhabricatorFile())->loadOneWhere(
      'id = %d',
      $this->id);
    if (!$file) {
      return new Aphront404Response();
    }

    if (($user->getPHID() != $file->getAuthorPHID()) &&
        (!$user->getIsAdmin())) {
      return new Aphront403Response();
    }

    if ($request->isFormPost()) {
      $file->delete();
      return id(new AphrontRedirectResponse())->setURI('/file/');
    }

    $dialog = new AphrontDialogView();
    $dialog->setUser($user);
    $dialog->setTitle('Really delete file?');
    $dialog->appendChild(hsprintf(
      "<p>Permanently delete '%s'? This action can not be undone.</p>",
      $file->getName()));
    $dialog->addSubmitButton('Delete');
    $dialog->addCancelButton($file->getInfoURI());

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }
}
