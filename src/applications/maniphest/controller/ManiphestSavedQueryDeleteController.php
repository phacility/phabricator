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
    $inst = pht(
      'Really delete the query "%s"? It will be lost forever!', $name);

    $dialog = id(new AphrontDialogView())
      ->setUser($user)
      ->setTitle(pht('Really delete this query?'))
      ->appendChild(hsprintf(
        '<p>%s</p>',
        $inst))
      ->addCancelButton('/maniphest/custom/')
      ->addSubmitButton(pht('Delete'));

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
