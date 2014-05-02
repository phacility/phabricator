<?php

final class PhabricatorDashboardPanelEditController
  extends PhabricatorDashboardController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    if ($this->id) {
      $is_create = false;

      $panel = id(new PhabricatorDashboardPanelQuery())
        ->setViewer($viewer)
        ->withIDs(array($this->id))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();
      if (!$panel) {
        return new Aphront404Response();
      }

    } else {
      $is_create = true;

      $panel = PhabricatorDashboardPanel::initializeNewPanel($viewer);

      $types = PhabricatorDashboardPanelType::getAllPanelTypes();
      $type = $request->getStr('type');
      if (empty($types[$type])) {
        return new Aphront404Response();
      }

      $panel->setPanelType($type);
    }

    if ($is_create) {
      $title = pht('New Panel');
      $header = pht('Create New Panel');
      $button = pht('Create Panel');
      $cancel_uri = $this->getApplicationURI('panel/');
    } else {
      $title = pht('Edit %s', $panel->getMonogram());
      $header = pht('Edit %s %s', $panel->getMonogram(), $panel->getName());
      $button = pht('Save Panel');
      $cancel_uri = '/'.$panel->getMonogram();
    }

    $v_name = $panel->getName();
    $e_name = true;

    $field_list = PhabricatorCustomField::getObjectFields(
      $panel,
      PhabricatorCustomField::ROLE_EDIT);
    $field_list
      ->setViewer($viewer)
      ->readFieldsFromStorage($panel);

    $validation_exception = null;
    if ($request->isFormPost()) {
      $v_name = $request->getStr('name');

      $xactions = array();

      $type_name = PhabricatorDashboardPanelTransaction::TYPE_NAME;
      $xactions[] = id(new PhabricatorDashboardPanelTransaction())
        ->setTransactionType($type_name)
        ->setNewValue($v_name);

      $field_xactions = $field_list->buildFieldTransactionsFromRequest(
        new PhabricatorDashboardPanelTransaction(),
        $request);
      $xactions = array_merge($xactions, $field_xactions);

      try {
        $editor = id(new PhabricatorDashboardPanelTransactionEditor())
          ->setActor($viewer)
          ->setContinueOnNoEffect(true)
          ->setContentSourceFromRequest($request)
          ->applyTransactions($panel, $xactions);

        return id(new AphrontRedirectResponse())
          ->setURI('/'.$panel->getMonogram());
      } catch (PhabricatorApplicationTransactionValidationException $ex) {
        $validation_exception = $ex;

        $e_name = $validation_exception->getShortMessage($type_name);
      }
    }

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Name'))
          ->setName('name')
          ->setValue($v_name)
          ->setError($e_name));

    $field_list->appendFieldsToForm($form);

    $form
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue($button)
          ->addCancelButton($cancel_uri));

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(
      pht('Panels'),
      $this->getApplicationURI('panel/'));
    if ($is_create) {
      $crumbs->addTextCrumb(pht('New Panel'));
      $form->addHiddenInput('type', $panel->getPanelType());
    } else {
      $crumbs->addTextCrumb(
        $panel->getMonogram(),
        '/'.$panel->getMonogram());
      $crumbs->addTextCrumb(pht('Edit'));
    }

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText($header)
      ->setValidationException($validation_exception)
      ->setForm($form);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
      ),
      array(
        'title' => $title,
        'device' => true,
      ));
  }

}
