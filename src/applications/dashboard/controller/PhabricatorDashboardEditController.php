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
      if (!$request->getStr('edit')) {
        if ($request->isFormPost()) {
          switch ($request->getStr('template')) {
            case 'empty':
              break;
            default:
              return $this->processBuildTemplateRequest($request);
          }
        } else {
          return $this->processTemplateRequest($request);
        }
      }

      $dashboard = PhabricatorDashboard::initializeNewDashboard($viewer);

      $is_new = true;
    }

    $crumbs = $this->buildApplicationCrumbs();

    if ($is_new) {
      $title = pht('Create Dashboard');
      $header = pht('Create Dashboard');
      $button = pht('Create Dashboard');
      $cancel_uri = $this->getApplicationURI();

      $crumbs->addTextCrumb(pht('Create Dashboard'));
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
    if ($request->isFormPost() && $request->getStr('edit')) {
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
      ->addHiddenInput('edit', true)
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
      ));
  }

  private function processTemplateRequest(AphrontRequest $request) {
    $viewer = $request->getUser();

    $template_control = id(new AphrontFormRadioButtonControl())
      ->setName(pht('template'))
      ->setValue($request->getStr('template', 'empty'))
      ->addButton(
        'empty',
        pht('Empty'),
        pht('Start with a blank canvas.'))
      ->addButton(
        'simple',
        pht('Simple Template'),
        pht(
          'Start with a simple dashboard with a welcome message, a feed of '.
          'recent events, and a few starter panels.'));

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendRemarkupInstructions(
        pht('Choose a dashboard template to start with.'))
      ->appendChild($template_control);

    return $this->newDialog()
      ->setTitle(pht('Create Dashboard'))
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->appendChild($form->buildLayoutView())
      ->addCancelButton('/dashboard/')
      ->addSubmitButton(pht('Continue'));
  }

  private function processBuildTemplateRequest(AphrontRequest $request) {
    $viewer = $request->getUser();
    $template = $request->getStr('template');

    $bare_panel = PhabricatorDashboardPanel::initializeNewPanel($viewer);
    $panel_phids = array();

    switch ($template) {
      case 'simple':
        $v_name = pht('New Simple Dashboard');

        $welcome_panel = $this->newPanel(
          $request,
          $viewer,
          'text',
          pht('Welcome'),
          array(
            'text' => pht(
              "This is a simple template dashboard. You can edit this panel ".
              "to change this text and replace it with a welcome message, or ".
              "leave this placeholder text as-is to give your dashboard a ".
              "rustic, authentic feel.\n\n".
              "You can drag, remove, add, and edit panels to customize the ".
              "rest of this dashboard to show the information you want.\n\n".
              "To install this dashboard on the home page, use the ".
              "**Install Dashboard** action link above."),
          ));
        $panel_phids[] = $welcome_panel->getPHID();

        $feed_panel = $this->newPanel(
          $request,
          $viewer,
          'query',
          pht('Recent Activity'),
          array(
            'class' => 'PhabricatorFeedSearchEngine',
            'key' => 'all',
          ));
        $panel_phids[] = $feed_panel->getPHID();

        $task_panel = $this->newPanel(
          $request,
          $viewer,
          'query',
          pht('Recent Tasks'),
          array(
            'class' => 'ManiphestTaskSearchEngine',
            'key' => 'all',
          ));
        $panel_phids[] = $task_panel->getPHID();

        $commit_panel = $this->newPanel(
          $request,
          $viewer,
          'query',
          pht('Recent Commits'),
          array(
            'class' => 'PhabricatorCommitSearchEngine',
            'key' => 'all',
          ));
        $panel_phids[] = $commit_panel->getPHID();

        $mode_2_and_1 = PhabricatorDashboardLayoutConfig::MODE_THIRDS_AND_THIRD;
        $layout = id(new PhabricatorDashboardLayoutConfig())
          ->setLayoutMode($mode_2_and_1)
          ->setPanelLocation(0, $welcome_panel->getPHID())
          ->setPanelLocation(0, $task_panel->getPHID())
          ->setPanelLocation(0, $commit_panel->getPHID())
          ->setPanelLocation(1, $feed_panel->getPHID());

        break;
      default:
        throw new Exception(pht('Unknown dashboard template %s!', $template));
    }

    // Create the dashboard.

    $dashboard = PhabricatorDashboard::initializeNewDashboard($viewer)
      ->setLayoutConfigFromObject($layout);

    $xactions = array();

    $xactions[] = id(new PhabricatorDashboardTransaction())
      ->setTransactionType(PhabricatorDashboardTransaction::TYPE_NAME)
      ->setNewValue($v_name);

    $xactions[] = id(new PhabricatorDashboardTransaction())
      ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
      ->setMetadataValue(
        'edge:type',
        PhabricatorDashboardDashboardHasPanelEdgeType::EDGECONST)
      ->setNewValue(
        array(
          '+' => array_fuse($panel_phids),
        ));

    $editor = id(new PhabricatorDashboardTransactionEditor())
      ->setActor($viewer)
      ->setContinueOnNoEffect(true)
      ->setContentSourceFromRequest($request)
      ->applyTransactions($dashboard, $xactions);

    $manage_uri = $this->getApplicationURI('manage/'.$dashboard->getID().'/');

    return id(new AphrontRedirectResponse())
      ->setURI($manage_uri);
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
