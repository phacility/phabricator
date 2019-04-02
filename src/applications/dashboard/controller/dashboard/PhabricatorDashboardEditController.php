<?php

final class PhabricatorDashboardEditController
  extends PhabricatorDashboardController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    if ($id) {
      $dashboard = id(new PhabricatorDashboardQuery())
        ->setViewer($viewer)
        ->withIDs(array($id))
        ->needPanels(true)
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();
      if (!$dashboard) {
        return new Aphront404Response();
      }
      $v_projects = PhabricatorEdgeQuery::loadDestinationPHIDs(
        $dashboard->getPHID(),
        PhabricatorProjectObjectHasProjectEdgeType::EDGECONST);
      $v_projects = array_reverse($v_projects);
      $is_new = false;
    } else {
      $dashboard = PhabricatorDashboard::initializeNewDashboard($viewer);
      $v_projects = array();
      $is_new = true;
    }

    $crumbs = $this->buildApplicationCrumbs();

    if ($is_new) {
      $title = pht('Create Dashboard');
      $header_icon = 'fa-plus-square';
      $button = pht('Create Dashboard');
      $cancel_uri = $this->getApplicationURI();

      $crumbs->addTextCrumb(pht('Create Dashboard'));
    } else {
      $cancel_uri = $dashboard->getURI();

      $title = pht('Edit Dashboard: %s', $dashboard->getName());
      $header_icon = 'fa-pencil';
      $button = pht('Save Changes');

      $crumbs->addTextCrumb($dashboard->getName(), $cancel_uri);
      $crumbs->addTextCrumb(pht('Edit'));
    }

    $v_name = $dashboard->getName();
    $v_icon = $dashboard->getIcon();
    $v_layout_mode = $dashboard->getLayoutConfigObject()->getLayoutMode();
    $e_name = true;

    $validation_exception = null;
    if ($request->isFormPost() && $request->getStr('edit')) {
      $v_name = $request->getStr('name');
      $v_icon = $request->getStr('icon');
      $v_layout_mode = $request->getStr('layout_mode');
      $v_view_policy = $request->getStr('viewPolicy');
      $v_edit_policy = $request->getStr('editPolicy');
      $v_projects = $request->getArr('projects');

      $xactions = array();

      $type_name = PhabricatorDashboardTransaction::TYPE_NAME;
      $type_icon = PhabricatorDashboardTransaction::TYPE_ICON;
      $type_layout_mode = PhabricatorDashboardTransaction::TYPE_LAYOUT_MODE;
      $type_view_policy = PhabricatorTransactions::TYPE_VIEW_POLICY;
      $type_edit_policy = PhabricatorTransactions::TYPE_EDIT_POLICY;

      $xactions[] = id(new PhabricatorDashboardTransaction())
        ->setTransactionType($type_name)
        ->setNewValue($v_name);
      $xactions[] = id(new PhabricatorDashboardTransaction())
        ->setTransactionType($type_layout_mode)
        ->setNewValue($v_layout_mode);
      $xactions[] = id(new PhabricatorDashboardTransaction())
        ->setTransactionType($type_icon)
        ->setNewValue($v_icon);
      $xactions[] = id(new PhabricatorDashboardTransaction())
        ->setTransactionType($type_view_policy)
        ->setNewValue($v_view_policy);
      $xactions[] = id(new PhabricatorDashboardTransaction())
        ->setTransactionType($type_edit_policy)
        ->setNewValue($v_edit_policy);

      $proj_edge_type = PhabricatorProjectObjectHasProjectEdgeType::EDGECONST;
      $xactions[] = id(new PhabricatorDashboardTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
        ->setMetadataValue('edge:type', $proj_edge_type)
        ->setNewValue(array('=' => array_fuse($v_projects)));

      try {
        $editor = id(new PhabricatorDashboardTransactionEditor())
          ->setActor($viewer)
          ->setContinueOnNoEffect(true)
          ->setContentSourceFromRequest($request)
          ->applyTransactions($dashboard, $xactions);

        return id(new AphrontRedirectResponse())->setURI($dashboard->getURI());
      } catch (PhabricatorApplicationTransactionValidationException $ex) {
        $validation_exception = $ex;

        $e_name = $validation_exception->getShortMessage($type_name);

        $dashboard->setViewPolicy($v_view_policy);
        $dashboard->setEditPolicy($v_edit_policy);
      }
    }

    $policies = id(new PhabricatorPolicyQuery())
      ->setViewer($viewer)
      ->setObject($dashboard)
      ->execute();

    $layout_mode_options =
      PhabricatorDashboardLayoutConfig::getLayoutModeSelectOptions();
    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->addHiddenInput('edit', true)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Name'))
          ->setName('name')
          ->setValue($v_name)
          ->setError($e_name))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Layout Mode'))
          ->setName('layout_mode')
          ->setValue($v_layout_mode)
          ->setOptions($layout_mode_options))
      ->appendChild(
        id(new PHUIFormIconSetControl())
          ->setLabel(pht('Icon'))
          ->setName('icon')
          ->setIconSet(new PhabricatorDashboardIconSet())
          ->setValue($v_icon))
      ->appendChild(
        id(new AphrontFormPolicyControl())
          ->setName('viewPolicy')
          ->setPolicyObject($dashboard)
          ->setCapability(PhabricatorPolicyCapability::CAN_VIEW)
          ->setPolicies($policies))
      ->appendChild(
        id(new AphrontFormPolicyControl())
          ->setName('editPolicy')
          ->setPolicyObject($dashboard)
          ->setCapability(PhabricatorPolicyCapability::CAN_EDIT)
          ->setPolicies($policies));

    $form->appendControl(
      id(new AphrontFormTokenizerControl())
        ->setLabel(pht('Tags'))
        ->setName('projects')
        ->setValue($v_projects)
        ->setDatasource(new PhabricatorProjectDatasource()));

    $form->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue($button)
          ->addCancelButton($cancel_uri));

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->setForm($form)
      ->setBackground(PHUIObjectBoxView::WHITE_CONFIG)
      ->setValidationException($validation_exception);

    $crumbs->setBorder(true);

    $view = id(new PHUITwoColumnView())
      ->setFooter($box);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

  private function newPanel(
    AphrontRequest $request,
    PhabricatorUser $viewer,
    $type,
    $name,
    array $properties) {

    $panel = PhabricatorDashboardPanel::initializeNewPanel($viewer)
      ->setPanelType($type)
      ->setProperties($properties);

    $xactions = array();

    $xactions[] = id(new PhabricatorDashboardPanelTransaction())
      ->setTransactionType(PhabricatorDashboardPanelTransaction::TYPE_NAME)
      ->setNewValue($name);

    $editor = id(new PhabricatorDashboardPanelTransactionEditor())
      ->setActor($viewer)
      ->setContinueOnNoEffect(true)
      ->setContentSourceFromRequest($request)
      ->applyTransactions($panel, $xactions);

    return $panel;
  }

}
