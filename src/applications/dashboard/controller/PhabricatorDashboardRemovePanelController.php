<?php

final class PhabricatorDashboardRemovePanelController
  extends PhabricatorDashboardController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $dashboard = id(new PhabricatorDashboardQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$dashboard) {
      return new Aphront404Response();
    }

    // NOTE: If you can edit a dashboard, you can remove panels from it even
    // if you don't have permission to see them or they aren't valid. We only
    // require that the panel be present on the dashboard.

    $v_panel = $request->getStr('panelPHID');

    $panel_on_dashboard = false;
    $layout = $dashboard->getLayoutConfigObject();
    $columns = $layout->getPanelLocations();
    foreach ($columns as $column) {
      foreach ($column as $column_panel_phid) {
        if ($column_panel_phid == $v_panel) {
          $panel_on_dashboard = true;
          break;
        }
      }
    }

    if (!$panel_on_dashboard) {
      return new Aphront404Response();
    }

    $redirect_uri = $this->getApplicationURI(
      'arrange/'.$dashboard->getID().'/');
    $layout_config = $dashboard->getLayoutConfigObject();

    if ($request->isFormPost()) {
      $xactions = array();
      $xactions[] = id(new PhabricatorDashboardTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
        ->setMetadataValue(
          'edge:type',
          PhabricatorDashboardDashboardHasPanelEdgeType::EDGECONST)
          ->setNewValue(
            array(
              '-' => array(
                $v_panel => $v_panel,
              ),
            ));

      $layout_config->removePanel($v_panel);
      $dashboard->setLayoutConfigFromObject($layout_config);

      $editor = id(new PhabricatorDashboardTransactionEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnMissingFields(true)
        ->setContinueOnNoEffect(true)
        ->applyTransactions($dashboard, $xactions);

      return id(new AphrontRedirectResponse())->setURI($redirect_uri);
    }

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->addHiddenInput('confirm', true)
      ->addHiddenInput('panelPHID', $v_panel)
      ->appendChild(pht('Are you sure you want to remove this panel?'));

    return $this->newDialog()
      ->setTitle(pht('Remove Panel'))
      ->appendChild($form->buildLayoutView())
      ->addCancelButton($redirect_uri)
      ->addSubmitButton(pht('Remove Panel'));
  }

}
