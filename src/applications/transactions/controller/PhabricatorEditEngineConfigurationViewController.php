<?php

final class PhabricatorEditEngineConfigurationViewController
  extends PhabricatorEditEngineController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $config = $this->loadConfigForView();
    if (!$config) {
      return id(new Aphront404Response());
    }

    $is_concrete = (bool)$config->getID();

    $curtain = $this->buildCurtainView($config);
    $properties = $this->buildPropertyView($config);

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setPolicyObject($config)
      ->setHeader(pht('Edit Form: %s', $config->getDisplayName()))
      ->setHeaderIcon('fa-pencil');

    if ($config->getIsDisabled()) {
      $name = pht('Disabled');
      $icon = 'fa-ban';
      $color = 'indigo';
    } else {
      $name = pht('Enabled');
      $icon = 'fa-check';
      $color = 'green';
    }
    $header->setStatus($icon, $color, $name);

    $field_list = $this->buildFieldList($config);
    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->setBorder(true);

    if ($is_concrete) {
      $title = pht('Form %d', $config->getID());
      $crumbs->addTextCrumb($title);
    } else {
      $title = pht('Builtin');
      $crumbs->addTextCrumb(pht('Builtin'));
    }

    if ($is_concrete) {
      $timeline = $this->buildTransactionTimeline(
        $config,
        new PhabricatorEditEngineConfigurationTransactionQuery());

      $timeline->setShouldTerminate(true);
    } else {
      $timeline = null;
    }

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->setMainColumn(array(
        $field_list,
        $timeline,
      ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

  private function buildCurtainView(
    PhabricatorEditEngineConfiguration $config) {
    $viewer = $this->getViewer();
    $engine = $config->getEngine();
    $engine_key = $engine->getEngineKey();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $config,
      PhabricatorPolicyCapability::CAN_EDIT);

    $curtain = $this->newCurtainView($config);
    $form_key = $config->getIdentifier();

    $base_uri = "/transactions/editengine/{$engine_key}";

    $is_concrete = (bool)$config->getID();
    if (!$is_concrete) {
      $save_uri = "{$base_uri}/save/{$form_key}/";

      $curtain->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Make Editable'))
          ->setIcon('fa-pencil')
          ->setDisabled(!$can_edit)
          ->setWorkflow(true)
          ->setHref($save_uri));

      $can_edit = false;
    } else {
      $edit_uri = "{$base_uri}/edit/{$form_key}/";
      $curtain->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Edit Form Configuration'))
          ->setIcon('fa-pencil')
          ->setDisabled(!$can_edit)
          ->setWorkflow(!$can_edit)
          ->setHref($edit_uri));
    }

    $use_uri = $engine->getEditURI(null, "form/{$form_key}/");

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Use Form'))
        ->setIcon('fa-th-list')
        ->setHref($use_uri));

    $defaults_uri = "{$base_uri}/defaults/{$form_key}/";

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Change Default Values'))
        ->setIcon('fa-paint-brush')
        ->setHref($defaults_uri)
        ->setWorkflow(!$can_edit)
        ->setDisabled(!$can_edit));

    $reorder_uri = "{$base_uri}/reorder/{$form_key}/";

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Change Field Order'))
        ->setIcon('fa-sort-alpha-asc')
        ->setHref($reorder_uri)
        ->setWorkflow(true)
        ->setDisabled(!$can_edit));

    $lock_uri = "{$base_uri}/lock/{$form_key}/";

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Lock / Hide Fields'))
        ->setIcon('fa-lock')
        ->setHref($lock_uri)
        ->setWorkflow(true)
        ->setDisabled(!$can_edit));

    if ($engine->supportsSubtypes()) {
      $subtype_uri = "{$base_uri}/subtype/{$form_key}/";

      $curtain->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Change Form Subtype'))
          ->setIcon('fa-drivers-license-o')
          ->setHref($subtype_uri)
          ->setWorkflow(true)
          ->setDisabled(!$can_edit));
    }

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Change Default Values'))
        ->setIcon('fa-paint-brush')
        ->setHref($defaults_uri)
        ->setWorkflow(!$can_edit)
        ->setDisabled(!$can_edit));


    $disable_uri = "{$base_uri}/disable/{$form_key}/";

    if ($config->getIsDisabled()) {
      $disable_name = pht('Enable Form');
      $disable_icon = 'fa-check';
    } else {
      $disable_name = pht('Disable Form');
      $disable_icon = 'fa-ban';
    }

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName($disable_name)
        ->setIcon($disable_icon)
        ->setHref($disable_uri)
        ->setWorkflow(true)
        ->setDisabled(!$can_edit));

    $defaultcreate_uri = "{$base_uri}/defaultcreate/{$form_key}/";

    if ($config->getIsDefault()) {
      $defaultcreate_name = pht('Unmark as "Create" Form');
      $defaultcreate_icon = 'fa-minus';
    } else {
      $defaultcreate_name = pht('Mark as "Create" Form');
      $defaultcreate_icon = 'fa-plus';
    }

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName($defaultcreate_name)
        ->setIcon($defaultcreate_icon)
        ->setHref($defaultcreate_uri)
        ->setWorkflow(true)
        ->setDisabled(!$can_edit));

    if ($config->getIsEdit()) {
      $isedit_name = pht('Unmark as "Edit" Form');
      $isedit_icon = 'fa-minus';
    } else {
      $isedit_name = pht('Mark as "Edit" Form');
      $isedit_icon = 'fa-plus';
    }

    $isedit_uri = "{$base_uri}/defaultedit/{$form_key}/";

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName($isedit_name)
        ->setIcon($isedit_icon)
        ->setHref($isedit_uri)
        ->setWorkflow(true)
        ->setDisabled(!$can_edit));

    return $curtain;
  }

  private function buildPropertyView(
    PhabricatorEditEngineConfiguration $config) {
    $viewer = $this->getViewer();

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer);

    return $properties;
  }

  private function buildFieldList(PhabricatorEditEngineConfiguration $config) {
    $viewer = $this->getViewer();
    $engine = $config->getEngine();

    $fields = $engine->getFieldsForConfig($config);

    $form = id(new AphrontFormView())
       ->setUser($viewer)
       ->setAction(null);

    foreach ($fields as $field) {
      $field->setIsPreview(true);

      $field->appendToForm($form);
    }

    $info = id(new PHUIInfoView())
      ->setSeverity(PHUIInfoView::SEVERITY_WARNING)
      ->setErrors(
        array(
          pht('This is a preview of the current form configuration.'),
        ));

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Form Preview'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setForm($form);

    return array($info, $box);
  }

}
