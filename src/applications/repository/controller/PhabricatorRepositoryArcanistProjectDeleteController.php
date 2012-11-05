<?php

final class PhabricatorRepositoryArcanistProjectDeleteController
  extends PhabricatorRepositoryController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {

    $arc_project =
      id(new PhabricatorRepositoryArcanistProject())->load($this->id);
    if (!$arc_project) {
      return new Aphront404Response();
    }

    $request = $this->getRequest();

    if ($request->isDialogFormPost()) {
      $arc_project->delete();
      return id(new AphrontRedirectResponse())->setURI('/repository/');
    }

    $dialog = new AphrontDialogView();
    $dialog
      ->setUser($request->getUser())
      ->setTitle('Really delete this arcanist project?')
      ->appendChild(
        '<p>Really delete the "'.phutil_escape_html($arc_project->getName()).
        '" arcanist project? '.
        'This operation can not be undone.</p>')
      ->setSubmitURI('/repository/project/delete/'.$this->id.'/')
      ->addSubmitButton('Delete Arcanist Project')
      ->addCancelButton('/repository/');

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }
}
