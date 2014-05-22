<?php

final class PhabricatorDashboardEditController
  extends PhabricatorDashboardController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    if ($this->id) {
      $dashboard = id(new PhabricatorDashboardQuery())
        ->setViewer($viewer)
        ->withIDs(array($this->id))
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

      $is_new = false;
    } else {
      $dashboard = PhabricatorDashboard::initializeNewDashboard($viewer);

      $is_new = true;
    }

    $crumbs = $this->buildApplicationCrumbs();

    if ($is_new) {
      $title = pht('Create Dashboard');
      $header = pht('Create Dashboard');
      $button = pht('Create Dashboard');
      $cancel_uri = $this->getApplicationURI();

      $crumbs->addTextCrumb('Create Dashboard');
    } else {
      $id = $dashboard->getID();
      $cancel_uri = $this->getApplicationURI('manage/'.$id.'/');

      $title = pht('Edit Dashboard %d', $dashboard->getID());
      $header = pht('Edit Dashboard "%s"', $dashboard->getName());
      $button = pht('Save Changes');

      $crumbs->addTextCrumb(pht('Dashboard %d', $id), $cancel_uri);
      $crumbs->addTextCrumb(pht('Edit'));
    }

    $v_name = $dashboard->getName();
    $v_layout_mode = $dashboard->getLayoutConfigObject()->getLayoutMode();
    $e_name = true;

    $validation_exception = null;
    if ($request->isFormPost()) {
      $v_name = $request->getStr('name');
      $v_layout_mode = $request->getStr('layout_mode');
      $v_view_policy = $request->getStr('viewPolicy');
      $v_edit_policy = $request->getStr('editPolicy');

      $xactions = array();

      $type_name = PhabricatorDashboardTransaction::TYPE_NAME;
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
        ->setTransactionType($type_view_policy)
        ->setNewValue($v_view_policy);
      $xactions[] = id(new PhabricatorDashboardTransaction())
        ->setTransactionType($type_edit_policy)
        ->setNewValue($v_edit_policy);

      try {
        $editor = id(new PhabricatorDashboardTransactionEditor())
          ->setActor($viewer)
          ->setContinueOnNoEffect(true)
          ->setContentSourceFromRequest($request)
          ->applyTransactions($dashboard, $xactions);

        $uri = $this->getApplicationURI('manage/'.$dashboard->getID().'/');

        return id(new AphrontRedirectResponse())->setURI($uri);
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
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Name'))
          ->setName('name')
          ->setValue($v_name)
          ->setError($e_name))
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
          ->setPolicies($policies))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Layout Mode'))
          ->setName('layout_mode')
          ->setValue($v_layout_mode)
          ->setOptions($layout_mode_options))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue($button)
          ->addCancelButton($cancel_uri));

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText($header)
      ->setForm($form)
      ->setValidationException($validation_exception);

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
