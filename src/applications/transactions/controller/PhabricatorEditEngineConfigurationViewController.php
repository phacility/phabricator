<?php

final class PhabricatorEditEngineConfigurationViewController
  extends PhabricatorEditEngineController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $engine_key = $request->getURIData('engineKey');
    $this->setEngineKey($engine_key);

    $key = $request->getURIData('key');
    $viewer = $this->getViewer();

    $config = id(new PhabricatorEditEngineConfigurationQuery())
      ->setViewer($viewer)
      ->withEngineKeys(array($engine_key))
      ->withIdentifiers(array($key))
      ->executeOne();
    if (!$config) {
      return id(new Aphront404Response());
    }

    $is_concrete = (bool)$config->getID();

    $actions = $this->buildActionView($config);

    $properties = $this->buildPropertyView($config)
      ->setActionList($actions);

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setPolicyObject($config)
      ->setHeader(pht('Edit Form: %s', $config->getDisplayName()));

    $box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    $crumbs = $this->buildApplicationCrumbs();

    if ($is_concrete) {
      $crumbs->addTextCrumb(pht('Form %d', $config->getID()));
    } else {
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

    return $this->newPage()
      ->setCrumbs($crumbs)
      ->appendChild(
        array(
          $box,
          $timeline,
        ));
  }

  private function buildActionView(
    PhabricatorEditEngineConfiguration $config) {
    $viewer = $this->getViewer();
    $engine_key = $this->getEngineKey();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $config,
      PhabricatorPolicyCapability::CAN_EDIT);

    $view = id(new PhabricatorActionListView())
      ->setUser($viewer);

    $key = $config->getIdentifier();

    $base_uri = "/transactions/editengine/{$engine_key}";

    $is_concrete = (bool)$config->getID();
    if (!$is_concrete) {
      $save_uri = "{$base_uri}/save/{$key}/";

      $view->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Make Editable'))
          ->setIcon('fa-pencil')
          ->setDisabled(!$can_edit)
          ->setWorkflow(true)
          ->setHref($save_uri));

      $can_edit = false;
    } else {
      $edit_uri = "{$base_uri}/edit/{$key}/";
      $view->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Edit Form Configuration'))
          ->setIcon('fa-pencil')
          ->setDisabled(!$can_edit)
          ->setWorkflow(!$can_edit)
          ->setHref($edit_uri));
    }

    return $view;
  }

  private function buildPropertyView(
    PhabricatorEditEngineConfiguration $config) {
    $viewer = $this->getViewer();

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($config);

    return $properties;
  }


}
