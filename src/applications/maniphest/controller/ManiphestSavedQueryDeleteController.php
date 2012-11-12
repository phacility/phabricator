<?php

/**
 * @group maniphest
 */
final class ManiphestSavedQueryDeleteController extends ManiphestController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $id = $this->id;
    $query = id(new ManiphestSavedQuery())->load($id);
    if (!$query) {
      return new Aphront404Response();
    }
    if ($query->getUserPHID() != $user->getPHID()) {
      return new Aphront400Response();
    }

    if ($request->isDialogFormPost()) {
      $query->delete();
      return id(new AphrontRedirectResponse())->setURI('/maniphest/custom/');
    }

    $name = $query->getName();

    $dialog = id(new AphrontDialogView())
      ->setUser($user)
      ->setTitle('Really delete this query?')
      ->appendChild(
        '<p>'.
          'Really delete the query "'.phutil_escape_html($name).'"? '.
          'It will be lost forever!'.
        '</p>')
      ->addCancelButton('/maniphest/custom/')
      ->addSubmitButton('Delete');

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
