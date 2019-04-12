<?php

final class PhabricatorDashboardQueryPanelInstallController
  extends PhabricatorDashboardController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $v_dashboard = null;
    $e_dashboard = null;

    $v_name = null;
    $e_name = true;

    $v_engine = $request->getStr('engine');
    if (!strlen($v_engine)) {
      $v_engine = $request->getURIData('engineKey');
    }

    $v_query = $request->getStr('query');
    if (!strlen($v_query)) {
      $v_query = $request->getURIData('queryKey');
    }

    $engines = PhabricatorApplicationSearchEngine::getAllEngines();
    $engine = idx($engines, $v_engine);
    if ($engine) {
      $engine = id(clone $engine)
        ->setViewer($viewer);

      $redirect_uri = $engine->getQueryResultsPageURI($v_query);

      $named_query = idx($engine->loadEnabledNamedQueries(), $v_query);
      if ($named_query) {
        $v_name = $named_query->getQueryName();
      }
    } else {
      $redirect_uri = '/';
    }

    $errors = array();

    $xaction_name = PhabricatorDashboardPanelNameTransaction::TRANSACTIONTYPE;
    $xaction_engine =
      PhabricatorDashboardQueryPanelApplicationTransaction::TRANSACTIONTYPE;
    $xaction_query =
      PhabricatorDashboardQueryPanelQueryTransaction::TRANSACTIONTYPE;

    if ($request->isFormPost()) {
      $v_name = $request->getStr('name');
      if (!$v_name) {
        $errors[] = pht('You must provide a name for this panel.');
        $e_name = pht('Required');
      }

      $v_dashboard = head($request->getArr('dashboardPHIDs'));
      if (!$v_dashboard) {
        $errors[] = pht('You must select a dashboard.');
        $e_dashboard = pht('Required');
      } else {
        $dashboard = id(new PhabricatorDashboardQuery())
          ->setViewer($viewer)
          ->withPHIDs(array($v_dashboard))
          ->executeOne();
        if (!$dashboard) {
          $errors[] = pht('You must select a valid dashboard.');
          $e_dashboard = pht('Invalid');
        }

        $can_edit = PhabricatorPolicyFilter::hasCapability(
          $viewer,
          $dashboard,
          PhabricatorPolicyCapability::CAN_EDIT);
        if (!$can_edit) {
          $errors[] = pht(
            'You must select a dashboard you have permission to edit.');
        }
      }

      if (!$errors) {
        $done_uri = $dashboard->getURI();

        // First, create a new panel.

        $panel_type = id(new PhabricatorDashboardQueryPanelType())
          ->getPanelTypeKey();

        $panel = PhabricatorDashboardPanel::initializeNewPanel($viewer)
          ->setPanelType($panel_type);

        $xactions = array();

        $xactions[] = $panel->getApplicationTransactionTemplate()
          ->setTransactionType($xaction_engine)
          ->setNewValue($v_engine);

        $xactions[] = $panel->getApplicationTransactionTemplate()
          ->setTransactionType($xaction_query)
          ->setNewValue($v_query);

        $xactions[] = $panel->getApplicationTransactionTemplate()
          ->setTransactionType($xaction_name)
          ->setNewValue($v_name);

        $editor = $panel->getApplicationTransactionEditor()
          ->setActor($viewer)
          ->setContentSourceFromRequest($request)
          ->applyTransactions($panel, $xactions);

        // Now that we've created a panel, add it to the dashboard.

        $xactions = array();

        $ref_list = clone $dashboard->getPanelRefList();
        $ref_list->newPanelRef($panel);
        $new_panels = $ref_list->toDictionary();

        $xactions[] = $dashboard->getApplicationTransactionTemplate()
          ->setTransactionType(
            PhabricatorDashboardPanelsTransaction::TRANSACTIONTYPE)
          ->setNewValue($new_panels);

        $editor = $dashboard->getApplicationTransactionEditor()
          ->setActor($viewer)
          ->setContentSourceFromRequest($request)
          ->setContinueOnNoEffect(true)
          ->setContinueOnMissingFields(true)
          ->applyTransactions($dashboard, $xactions);

        return id(new AphrontRedirectResponse())->setURI($done_uri);
      }
    }

    if ($v_dashboard) {
      $dashboard_phids = array($v_dashboard);
    } else {
      $dashboard_phids = array();
    }

    $form = id(new AphrontFormView())
      ->setViewer($viewer)
      ->appendControl(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Name'))
          ->setName('name')
          ->setValue($v_name)
          ->setError($e_name))
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setValue($dashboard_phids)
          ->setError($e_dashboard)
          ->setName('dashboardPHIDs')
          ->setLimit(1)
          ->setDatasource(new PhabricatorDashboardDatasource())
          ->setLabel(pht('Dashboard')));

    return $this->newDialog()
      ->setTitle(pht('Add Panel to Dashboard'))
      ->setErrors($errors)
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->addHiddenInput('engine', $v_engine)
      ->addHiddenInput('query', $v_query)
      ->appendForm($form)
      ->addCancelButton($redirect_uri)
      ->addSubmitButton(pht('Add Panel'));

  }

}
