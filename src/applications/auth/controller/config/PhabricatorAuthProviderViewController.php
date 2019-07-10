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

    $status = $this->buildStatus($config);
    $view->addProperty(pht('Status'), $status);

    return $view;
  }

  private function buildStatus(PhabricatorAuthProviderConfig $config) {
    $viewer = $this->getViewer();
    $view = id(new PHUIStatusListView())
      ->setViewer($viewer);

    $icon_enabled = PHUIStatusItemView::ICON_ACCEPT;
    $icon_disabled = PHUIStatusItemView::ICON_REJECT;

    $icon_map = array(
      true => $icon_enabled,
      false => $icon_disabled,
    );

    $color_map = array(
      true => 'green',
      false => 'red',
    );

    $provider = $config->getProvider();

    $view->addItem(
      id(new PHUIStatusItemView())
        ->setIcon(
          $icon_map[$config->getIsEnabled()],
          $color_map[$config->getIsEnabled()])
        ->setTarget(pht('Provider Enabled')));

    $view->addItem(
      id(new PHUIStatusItemView())
        ->setIcon(
          $icon_map[$config->getShouldAllowLogin()],
          $color_map[$config->getShouldAllowLogin()])
        ->setTarget(pht('Allow Logins')));

    $view->addItem(
      id(new PHUIStatusItemView())
        ->setIcon(
          $icon_map[$config->getShouldAllowRegistration()],
          $color_map[$config->getShouldAllowRegistration()])
        ->setTarget(pht('Allow Registration')));

    $view->addItem(
      id(new PHUIStatusItemView())
        ->setIcon(
          $icon_map[$config->getShouldAllowLink()],
          $color_map[$config->getShouldAllowLink()])
        ->setTarget(pht('Allow Account Linking')));

    $view->addItem(
      id(new PHUIStatusItemView())
        ->setIcon(
          $icon_map[$config->getShouldAllowUnlink()],
          $color_map[$config->getShouldAllowUnlink()])
        ->setTarget(pht('Allow Account Unlinking')));

    if ($provider->shouldAllowEmailTrustConfiguration()) {
      $view->addItem(
        id(new PHUIStatusItemView())
          ->setIcon(
            $icon_map[$config->getShouldTrustEmails()],
            $color_map[$config->getShouldTrustEmails()])
          ->setTarget(pht('Trust Email Addresses')));
    }

    if ($provider->supportsAutoLogin()) {
      $view->addItem(
        id(new PHUIStatusItemView())
          ->setIcon(
            $icon_map[$config->getShouldAutoLogin()],
            $color_map[$config->getShouldAutoLogin()])
          ->setTarget(pht('Allow Auto Login')));
    }

    return $view;
  }

}
