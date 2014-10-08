<?php

final class PhabricatorSearchEditController
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

    $engine = $saved_query->newEngine()->setViewer($user);

    $complete_uri = $engine->getQueryManagementURI();
    $cancel_uri = $complete_uri;

    $named_query = id(new PhabricatorNamedQueryQuery())
      ->setViewer($user)
      ->withQueryKeys(array($saved_query->getQueryKey()))
      ->withUserPHIDs(array($user->getPHID()))
      ->executeOne();
    if (!$named_query) {
      $named_query = id(new PhabricatorNamedQuery())
        ->setUserPHID($user->getPHID())
        ->setQueryKey($saved_query->getQueryKey())
        ->setEngineClassName($saved_query->getEngineClassName());

      // If we haven't saved the query yet, this is a "Save..." operation, so
      // take the user back to the query if they cancel instead of back to the
      // management interface.
      $cancel_uri = $engine->getQueryResultsPageURI(
        $saved_query->getQueryKey());
    }

    $e_name = true;
    $errors = array();

    if ($request->isFormPost()) {
      $named_query->setQueryName($request->getStr('name'));
      if (!strlen($named_query->getQueryName())) {
        $e_name = pht('Required');
        $errors[] = pht('You must name the query.');
      } else {
        $e_name = null;
      }

      if (!$errors) {
        $named_query->save();
        return id(new AphrontRedirectResponse())->setURI($complete_uri);
      }
    }

    $form = id(new AphrontFormView())
      ->setUser($user);

    $form->appendChild(
      id(new AphrontFormTextControl())
        ->setName('name')
        ->setLabel(pht('Query Name'))
        ->setValue($named_query->getQueryName())
        ->setError($e_name));

    $form->appendChild(
      id(new AphrontFormSubmitControl())
        ->setValue(pht('Save Query'))
        ->addCancelButton($cancel_uri));

    if ($named_query->getID()) {
      $title = pht('Edit Saved Query');
    } else {
      $title = pht('Save Query');
    }

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->setFormErrors($errors)
      ->setForm($form);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($title);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $form_box,
      ),
      array(
        'title' => $title,
      ));
  }

}
