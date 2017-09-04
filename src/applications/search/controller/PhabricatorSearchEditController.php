<?php

final class PhabricatorSearchEditController
  extends PhabricatorSearchBaseController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $id = $request->getURIData('id');
    if ($id) {
      $named_query = id(new PhabricatorNamedQueryQuery())
        ->setViewer($viewer)
        ->withIDs(array($id))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();
      if (!$named_query) {
        return new Aphront404Response();
      }

      $query_key = $named_query->getQueryKey();
    } else {
      $query_key = $request->getURIData('queryKey');
      $named_query = null;
    }

    $saved_query = id(new PhabricatorSavedQueryQuery())
      ->setViewer($viewer)
      ->withQueryKeys(array($query_key))
      ->executeOne();
    if (!$saved_query) {
      return new Aphront404Response();
    }

    $engine = $saved_query->newEngine()->setViewer($viewer);

    $complete_uri = $engine->getQueryManagementURI();
    $cancel_uri = $complete_uri;

    if (!$named_query) {
      $named_query = id(new PhabricatorNamedQuery())
        ->setUserPHID($viewer->getPHID())
        ->setQueryKey($saved_query->getQueryKey())
        ->setEngineClassName($saved_query->getEngineClassName());

      // If we haven't saved the query yet, this is a "Save..." operation, so
      // take the user back to the query if they cancel instead of back to the
      // management interface.
      $cancel_uri = $engine->getQueryResultsPageURI(
        $saved_query->getQueryKey());

      $is_new = true;
    } else {
      $is_new = false;
    }

    $can_global = ($viewer->getIsAdmin() && $is_new);

    $v_global = false;

    $e_name = true;
    $errors = array();

    if ($request->isFormPost()) {
      if ($can_global) {
        $v_global = $request->getBool('global');
        if ($v_global) {
          $named_query->setUserPHID(PhabricatorNamedQuery::SCOPE_GLOBAL);
        }
      }

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
      ->setUser($viewer);

    $form->appendChild(
      id(new AphrontFormTextControl())
        ->setName('name')
        ->setLabel(pht('Query Name'))
        ->setValue($named_query->getQueryName())
        ->setError($e_name));

    if ($can_global) {
      $form->appendChild(
        id(new AphrontFormCheckboxControl())
          ->addCheckbox(
            'global',
            '1',
            pht(
              'Save this query as a global query, making it visible to '.
              'all users.'),
            $v_global));
    }

    $form->appendChild(
      id(new AphrontFormSubmitControl())
        ->setValue(pht('Save Query'))
        ->addCancelButton($cancel_uri));

    if ($named_query->getID()) {
      $title = pht('Edit Saved Query');
      $header_icon = 'fa-pencil';
    } else {
      $title = pht('Save Query');
      $header_icon = 'fa-search';
    }

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Query'))
      ->setFormErrors($errors)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setForm($form);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($title);
    $crumbs->setBorder(true);

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setHeaderIcon($header_icon);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter($form_box);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);

  }

}
