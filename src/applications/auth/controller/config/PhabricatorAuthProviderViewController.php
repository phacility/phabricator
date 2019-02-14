<?php

final class PhabricatorAuthProviderViewController
  extends PhabricatorAuthProviderConfigController {

  public function handleRequest(AphrontRequest $request) {
    $this->requireApplicationCapability(
      AuthManageProvidersCapability::CAPABILITY);

    $viewer = $this->getViewer();
    $id = $request->getURIData('id');

    $config = id(new PhabricatorAuthProviderConfigQuery())
      ->setViewer($viewer)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->withIDs(array($id))
      ->executeOne();
    if (!$config) {
      return new Aphront404Response();
    }

    $header = $this->buildHeaderView($config);
    $properties = $this->buildPropertiesView($config);
    $curtain = $this->buildCurtain($config);

    $timeline = $this->buildTransactionTimeline(
      $config,
      new PhabricatorAuthProviderConfigTransactionQuery());
    $timeline->setShouldTerminate(true);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->addPropertySection(pht('Details'), $properties)
      ->setMainColumn($timeline);

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb($config->getObjectName())
      ->setBorder(true);

    return $this->newPage()
      ->setTitle(pht('Auth Provider: %s', $config->getDisplayName()))
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

  private function buildHeaderView(PhabricatorAuthProviderConfig $config) {
    $viewer = $this->getViewer();

    $view = id(new PHUIHeaderView())
      ->setViewer($viewer)
      ->setHeader($config->getDisplayName());

    if ($config->getIsEnabled()) {
      $view->setStatus('fa-check', 'bluegrey', pht('Enabled'));
    } else {
      $view->setStatus('fa-ban', 'red', pht('Disabled'));
    }

    return $view;
  }

  private function buildCurtain(PhabricatorAuthProviderConfig $config) {
    $viewer = $this->getViewer();
    $id = $config->getID();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $config,
      PhabricatorPolicyCapability::CAN_EDIT);

    $curtain = $this->newCurtainView($config);

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Auth Provider'))
        ->setIcon('fa-pencil')
        ->setHref($this->getApplicationURI("config/edit/{$id}/"))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    if ($config->getIsEnabled()) {
      $disable_uri = $this->getApplicationURI('config/disable/'.$id.'/');
      $disable_icon = 'fa-ban';
      $disable_text = pht('Disable Provider');
    } else {
      $disable_uri = $this->getApplicationURI('config/enable/'.$id.'/');
      $disable_icon = 'fa-check';
      $disable_text = pht('Enable Provider');
    }

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName($disable_text)
        ->setIcon($disable_icon)
        ->setHref($disable_uri)
        ->setDisabled(!$can_edit)
        ->setWorkflow(true));

    return $curtain;
  }

  private function buildPropertiesView(PhabricatorAuthProviderConfig $config) {
    $viewer = $this->getViewer();

    $view = id(new PHUIPropertyListView())
      ->setViewer($viewer);

    $view->addProperty(
      pht('Provider Type'),
      $config->getProvider()->getProviderName());

    return $view;
  }
}
