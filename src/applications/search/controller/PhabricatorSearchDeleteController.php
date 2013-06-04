<?php

/**
 * @group search
 */
final class PhabricatorSearchDeleteController
  extends PhabricatorSearchBaseController {

  private $queryKey;

  public function willProcessRequest(array $data) {
    $this->queryKey = idx($data, 'queryKey');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $saved_query = id(new PhabricatorSavedQueryQuery())
      ->setViewer($user)
      ->withQueryKeys(array($this->queryKey))
      ->executeOne();

    if (!$saved_query) {
      return new Aphront404Response();
    }

    $engine = $saved_query->newEngine();

    $named_query = id(new PhabricatorNamedQueryQuery())
      ->setViewer($user)
      ->withQueryKeys(array($saved_query->getQueryKey()))
      ->withUserPHIDs(array($user->getPHID()))
      ->executeOne();
    if (!$named_query) {
      return new Aphront404Response();
    }

    $return_uri = $engine->getQueryManagementURI();

    if ($request->isDialogFormPost()) {
      $named_query->delete();
      return id(new AphrontRedirectResponse())->setURI($return_uri);
    }

    $dialog = id(new AphrontDialogView())
      ->setUser($user)
      ->setTitle(pht("Really Delete Query?"))
      ->appendChild(
        pht(
          'Really delete the query "%s"? You can not undo this. Remember '.
          'all the great times you had filtering results together?',
          $named_query->getQueryName()))
      ->addCancelButton($return_uri)
      ->addSubmitButton(pht('Delete Query'));

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
