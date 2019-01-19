<?php

final class PhabricatorAuthFactorProviderViewController
  extends PhabricatorAuthFactorProviderController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $this->requireApplicationCapability(
      AuthManageProvidersCapability::CAPABILITY);

    $provider = id(new PhabricatorAuthFactorProviderQuery())
      ->setViewer($viewer)
      ->withIDs(array($request->getURIData('id')))
      ->executeOne();
    if (!$provider) {
      return new Aphront404Response();
    }

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb($provider->getObjectName())
      ->setBorder(true);

    $header = $this->buildHeaderView($provider);
    $properties = $this->buildPropertiesView($provider);
    $curtain = $this->buildCurtain($provider);


    $timeline = $this->buildTransactionTimeline(
      $provider,
      new PhabricatorAuthFactorProviderTransactionQuery());
    $timeline->setShouldTerminate(true);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->setMainColumn(
        array(
          $timeline,
        ))
      ->addPropertySection(pht('Details'), $properties);

    return $this->newPage()
      ->setTitle($provider->getDisplayName())
      ->setCrumbs($crumbs)
      ->setPageObjectPHIDs(
        array(
          $provider->getPHID(),
        ))
      ->appendChild($view);
  }

  private function buildHeaderView(PhabricatorAuthFactorProvider $provider) {
    $viewer = $this->getViewer();

    $view = id(new PHUIHeaderView())
      ->setViewer($viewer)
      ->setHeader($provider->getDisplayName())
      ->setPolicyObject($provider);

    return $view;
  }

  private function buildPropertiesView(
    PhabricatorAuthFactorProvider $provider) {
    $viewer = $this->getViewer();

    $view = id(new PHUIPropertyListView())
      ->setViewer($viewer);

    $view->addProperty(
      pht('Factor Type'),
      $provider->getFactor()->getFactorName());

    return $view;
  }

  private function buildCurtain(PhabricatorAuthFactorProvider $provider) {
    $viewer = $this->getViewer();
    $id = $provider->getID();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $provider,
      PhabricatorPolicyCapability::CAN_EDIT);

    $curtain = $this->newCurtainView($provider);

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit MFA Provider'))
        ->setIcon('fa-pencil')
        ->setHref($this->getApplicationURI("mfa/edit/{$id}/"))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    return $curtain;
  }

}
