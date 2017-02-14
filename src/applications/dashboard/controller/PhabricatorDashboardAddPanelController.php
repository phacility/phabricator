<?php

final class PhabricatorDashboardAddPanelController
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

    $redirect_uri = $this->getApplicationURI(
      'arrange/'.$dashboard->getID().'/');

    $v_panel = head($request->getArr('panel'));
    $e_panel = true;
    $errors = array();
    if ($request->isFormPost()) {
      if (strlen($v_panel)) {
        $panel = id(new PhabricatorDashboardPanelQuery())
          ->setViewer($viewer)
          ->withIDs(array($v_panel))
          ->executeOne();
        if (!$panel) {
          $errors[] = pht('No such panel!');
          $e_panel = pht('Invalid');
        }
      } else {
        $errors[] = pht('Select a panel to add.');
        $e_panel = pht('Required');
      }

      if (!$errors) {
        PhabricatorDashboardTransactionEditor::addPanelToDashboard(
          $viewer,
          PhabricatorContentSource::newFromRequest($request),
          $panel,
          $dashboard,
          $request->getInt('column', 0));

        return id(new AphrontRedirectResponse())->setURI($redirect_uri);
      }
    }

    $panels = id(new PhabricatorDashboardPanelQuery())
      ->setViewer($viewer)
      ->withArchived(false)
      ->execute();

    if (!$panels) {
      return $this->newDialog()
        ->setTitle(pht('No Panels Exist Yet'))
        ->appendParagraph(
          pht(
            'You have not created any dashboard panels yet, so you can not '.
            'add an existing panel.'))
        ->appendParagraph(
          pht('Instead, add a new panel.'))
        ->addCancelButton($redirect_uri);
    }

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->addHiddenInput('column', $request->getInt('column'))
      ->appendRemarkupInstructions(
        pht('Choose a panel to add to this dashboard:'))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setUser($this->getViewer())
          ->setDatasource(new PhabricatorDashboardPanelDatasource())
          ->setLimit(1)
          ->setName('panel')
          ->setLabel(pht('Panel'))
          ->setValue($v_panel));

    return $this->newDialog()
      ->setTitle(pht('Add Panel'))
      ->setErrors($errors)
      ->appendChild($form->buildLayoutView())
      ->addCancelButton($redirect_uri)
      ->addSubmitButton(pht('Add Panel'));
  }

}
