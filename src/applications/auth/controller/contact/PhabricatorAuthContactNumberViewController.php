<?php

final class PhabricatorAuthContactNumberViewController
  extends PhabricatorAuthContactNumberController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $number = id(new PhabricatorAuthContactNumberQuery())
      ->setViewer($viewer)
      ->withIDs(array($request->getURIData('id')))
      ->executeOne();
    if (!$number) {
      return new Aphront404Response();
    }

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb($number->getObjectName())
      ->setBorder(true);

    $header = $this->buildHeaderView($number);
    $properties = $this->buildPropertiesView($number);
    $curtain = $this->buildCurtain($number);

    $timeline = $this->buildTransactionTimeline(
      $number,
      new PhabricatorAuthContactNumberTransactionQuery());
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
      ->setTitle($number->getDisplayName())
      ->setCrumbs($crumbs)
      ->setPageObjectPHIDs(
        array(
          $number->getPHID(),
        ))
      ->appendChild($view);
  }

  private function buildHeaderView(PhabricatorAuthContactNumber $number) {
    $viewer = $this->getViewer();

    $view = id(new PHUIHeaderView())
      ->setViewer($viewer)
      ->setHeader($number->getObjectName())
      ->setPolicyObject($number);

    if ($number->isDisabled()) {
      $view->setStatus('fa-ban', 'red', pht('Disabled'));
    } else if ($number->getIsPrimary()) {
      $view->setStatus('fa-certificate', 'blue', pht('Primary'));
    }

    return $view;
  }

  private function buildPropertiesView(
    PhabricatorAuthContactNumber $number) {
    $viewer = $this->getViewer();

    $view = id(new PHUIPropertyListView())
      ->setViewer($viewer);

    $view->addProperty(
      pht('Owner'),
      $viewer->renderHandle($number->getObjectPHID()));

    $view->addProperty(pht('Contact Number'), $number->getDisplayName());

    return $view;
  }

  private function buildCurtain(PhabricatorAuthContactNumber $number) {
    $viewer = $this->getViewer();
    $id = $number->getID();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $number,
      PhabricatorPolicyCapability::CAN_EDIT);

    $curtain = $this->newCurtainView($number);

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Contact Number'))
        ->setIcon('fa-pencil')
        ->setHref($this->getApplicationURI("contact/edit/{$id}/"))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Send Test Message'))
        ->setIcon('fa-envelope-o')
        ->setHref($this->getApplicationURI("contact/test/{$id}/"))
        ->setDisabled(!$can_edit)
        ->setWorkflow(true));

    if ($number->isDisabled()) {
      $disable_uri = $this->getApplicationURI("contact/enable/{$id}/");
      $disable_name = pht('Enable Contact Number');
      $disable_icon = 'fa-check';
      $can_primary = false;
    } else {
      $disable_uri = $this->getApplicationURI("contact/disable/{$id}/");
      $disable_name = pht('Disable Contact Number');
      $disable_icon = 'fa-ban';
      $can_primary = !$number->getIsPrimary();
    }

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Make Primary Number'))
        ->setIcon('fa-certificate')
        ->setHref($this->getApplicationURI("contact/primary/{$id}/"))
        ->setDisabled(!$can_primary)
        ->setWorkflow(true));

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName($disable_name)
        ->setIcon($disable_icon)
        ->setHref($disable_uri)
        ->setWorkflow(true));

    return $curtain;
  }

}
