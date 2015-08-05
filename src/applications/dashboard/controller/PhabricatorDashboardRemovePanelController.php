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

    $v_panel = $request->getStr('panelPHID');
    $panel = id(new PhabricatorDashboardPanelQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($v_panel))
      ->executeOne();
    if (!$panel) {
      return new Aphront404Response();
    }

    $redirect_uri = $this->getApplicationURI(
      'manage/'.$dashboard->getID().'/');
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
                $panel->getPHID() => $panel->getPHID(),
              ),
            ));

      $layout_config->removePanel($panel->getPHID());
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
      ->setTitle(pht('Remove Panel %s', $panel->getMonogram()))
      ->appendChild($form->buildLayoutView())
      ->addCancelButton($redirect_uri)
      ->addSubmitButton(pht('Remove Panel'));
  }

}
