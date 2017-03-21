<?php

final class PhabricatorDashboardQueryPanelInstallController
  extends PhabricatorDashboardController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $v_dashboard = null;
    $v_name = null;
    $v_column = 0;
    $v_engine = $request->getURIData('engineKey');
    $v_query = $request->getURIData('queryKey');

    $e_name = true;

    // Validate Engines
    $engines = PhabricatorApplicationSearchEngine::getAllEngines();
    foreach ($engines as $name => $engine) {
      if (!$engine->canUseInPanelContext()) {
        unset($engines[$name]);
      }
    }
    if (!in_array($v_engine, array_keys($engines))) {
      return new Aphront404Response();
    }

    // Validate Queries
    $engine = $engines[$v_engine];
    $engine->setViewer($viewer);
    $good_query = false;
    if ($engine->isBuiltinQuery($v_query)) {
      $good_query = true;
    } else {
      $saved_query = id(new PhabricatorSavedQueryQuery())
        ->setViewer($viewer)
        ->withEngineClassNames(array($v_engine))
        ->withQueryKeys(array($v_query))
        ->executeOne();
      if ($saved_query) {
        $good_query = true;
      }
    }
    if (!$good_query) {
      return new Aphront404Response();
    }

    $named_query = idx($engine->loadEnabledNamedQueries(), $v_query);
    if ($named_query) {
      $v_name = $named_query->getQueryName();
    }

    $errors = array();

    if ($request->isFormPost()) {
      $v_dashboard = $request->getInt('dashboardID');
      $v_name = $request->getStr('name');
      if (!$v_name) {
        $errors[] = pht('You must provide a name for this panel.');
        $e_name = pht('Required');
      }

      $dashboard = id(new PhabricatorDashboardQuery())
        ->setViewer($viewer)
        ->withIDs(array($v_dashboard))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();

      if (!$dashboard) {
        $errors[] = pht('Please select a valid dashboard.');
      }

      if (!$errors) {
        $redirect_uri = "/dashboard/arrange/{$v_dashboard}/";

        $panel_type = id(new PhabricatorDashboardQueryPanelType())
          ->getPanelTypeKey();
        $panel = PhabricatorDashboardPanel::initializeNewPanel($viewer);
        $panel->setPanelType($panel_type);

        $field_list = PhabricatorCustomField::getObjectFields(
          $panel,
          PhabricatorCustomField::ROLE_EDIT);

        $field_list
          ->setViewer($viewer)
          ->readFieldsFromStorage($panel);

        $panel->requireImplementation()->initializeFieldsFromRequest(
          $panel,
          $field_list,
          $request);

        $xactions = array();

        $xactions[] = id(new PhabricatorDashboardPanelTransaction())
          ->setTransactionType(PhabricatorDashboardPanelTransaction::TYPE_NAME)
          ->setNewValue($v_name);

        $xactions[] = id(new PhabricatorDashboardPanelTransaction())
          ->setTransactionType(PhabricatorTransactions::TYPE_CUSTOMFIELD)
          ->setMetadataValue('customfield:key', 'std:dashboard:core:class')
          ->setOldValue(null)
          ->setNewValue($v_engine);

        $xactions[] = id(new PhabricatorDashboardPanelTransaction())
          ->setTransactionType(PhabricatorTransactions::TYPE_CUSTOMFIELD)
          ->setMetadataValue('customfield:key', 'std:dashboard:core:key')
          ->setOldValue(null)
          ->setNewValue($v_query);

        $editor = id(new PhabricatorDashboardPanelTransactionEditor())
          ->setActor($viewer)
          ->setContinueOnNoEffect(true)
          ->setContentSourceFromRequest($request)
          ->applyTransactions($panel, $xactions);

        PhabricatorDashboardTransactionEditor::addPanelToDashboard(
          $viewer,
          PhabricatorContentSource::newFromRequest($request),
          $panel,
          $dashboard,
          $request->getInt('column', 0));

        return id(new AphrontRedirectResponse())->setURI($redirect_uri);
      }
    }

    // Make this a select for now, as we don't expect someone to have
    // edit access to a vast number of dashboards.
    // Can add optiongroup if needed down the road.
    $dashboards = id(new PhabricatorDashboardQuery())
      ->setViewer($viewer)
      ->withStatuses(array(
        PhabricatorDashboard::STATUS_ACTIVE,
      ))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->execute();
    $options = mpull($dashboards, 'getName', 'getID');
    asort($options);

    $redirect_uri = $engine->getQueryResultsPageURI($v_query);

    if (!$options) {
      $notice = id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_NOTICE)
        ->appendChild(pht('You do not have access to any dashboards. To '.
        'continue, please create a dashboard first.'));

      return $this->newDialog()
        ->setTitle(pht('No Dashboards'))
        ->setWidth(AphrontDialogView::WIDTH_FORM)
        ->appendChild($notice)
        ->addCancelButton($redirect_uri);
    }

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->addHiddenInput('engine', $v_engine)
      ->addHiddenInput('query', $v_query)
      ->addHiddenInput('column', $v_column)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Name'))
          ->setName('name')
          ->setValue($v_name)
          ->setError($e_name))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setUser($this->getViewer())
          ->setValue($v_dashboard)
          ->setName('dashboardID')
          ->setOptions($options)
          ->setLabel(pht('Dashboard')));

    return $this->newDialog()
      ->setTitle(pht('Add Panel to Dashboard'))
      ->setErrors($errors)
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->appendChild($form->buildLayoutView())
      ->addCancelButton($redirect_uri)
      ->addSubmitButton(pht('Add Panel'));

  }

}
