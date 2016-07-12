<?php

final class FundInitiativeViewController
  extends FundController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $initiative = id(new FundInitiativeQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$initiative) {
      return new Aphront404Response();
    }

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($initiative->getMonogram());
    $crumbs->setBorder(true);

    $title = pht(
      '%s %s',
      $initiative->getMonogram(),
      $initiative->getName());

    if ($initiative->isClosed()) {
      $status_icon = 'fa-times';
      $status_color = 'bluegrey';
    } else {
      $status_icon = 'fa-check';
      $status_color = 'bluegrey';
    }
    $status_name = idx(
      FundInitiative::getStatusNameMap(),
      $initiative->getStatus());

    $header = id(new PHUIHeaderView())
      ->setHeader($initiative->getName())
      ->setUser($viewer)
      ->setPolicyObject($initiative)
      ->setStatus($status_icon, $status_color, $status_name)
      ->setHeaderIcon('fa-heart');

    $curtain = $this->buildCurtain($initiative);
    $details = $this->buildPropertySectionView($initiative);

    $timeline = $this->buildTransactionTimeline(
      $initiative,
      new FundInitiativeTransactionQuery());

    $add_comment = $this->buildCommentForm($initiative);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->setMainColumn(array(
        $timeline,
        $add_comment,
      ))
      ->addPropertySection(pht('Details'), $details);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->setPageObjectPHIDs(array($initiative->getPHID()))
      ->appendChild($view);
  }

  private function buildPropertySectionView(FundInitiative $initiative) {
    $viewer = $this->getRequest()->getUser();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer);

    $owner_phid = $initiative->getOwnerPHID();
    $merchant_phid = $initiative->getMerchantPHID();

    $view->addProperty(
      pht('Owner'),
      $viewer->renderHandle($owner_phid));

    $view->addProperty(
      pht('Payable to Merchant'),
      $viewer->renderHandle($merchant_phid));

    $view->addProperty(
      pht('Total Funding'),
      $initiative->getTotalAsCurrency()->formatForDisplay());

    $description = $initiative->getDescription();
    if (strlen($description)) {
      $description = new PHUIRemarkupView($viewer, $description);
      $view->addSectionHeader(
        pht('Description'), PHUIPropertyListView::ICON_SUMMARY);
      $view->addTextContent($description);
    }

    $risks = $initiative->getRisks();
    if (strlen($risks)) {
      $risks = new PHUIRemarkupView($viewer, $risks);
      $view->addSectionHeader(
        pht('Risks/Challenges'), 'fa-ambulance');
      $view->addTextContent($risks);
    }

    return $view;
  }

  private function buildCurtain(FundInitiative $initiative) {
    $viewer = $this->getViewer();

    $id = $initiative->getID();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $initiative,
      PhabricatorPolicyCapability::CAN_EDIT);

    $curtain = $this->newCurtainView($initiative);

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Initiative'))
        ->setIcon('fa-pencil')
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit)
        ->setHref($this->getApplicationURI("/edit/{$id}/")));

    if ($initiative->isClosed()) {
      $close_name = pht('Reopen Initiative');
      $close_icon = 'fa-check';
    } else {
      $close_name = pht('Close Initiative');
      $close_icon = 'fa-times';
    }

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName($close_name)
        ->setIcon($close_icon)
        ->setDisabled(!$can_edit)
        ->setWorkflow(true)
        ->setHref($this->getApplicationURI("/close/{$id}/")));

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Back Initiative'))
        ->setIcon('fa-money')
        ->setDisabled($initiative->isClosed())
        ->setWorkflow(true)
        ->setHref($this->getApplicationURI("/back/{$id}/")));

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('View Backers'))
        ->setIcon('fa-bank')
        ->setHref($this->getApplicationURI("/backers/{$id}/")));

    return $curtain;
  }

  private function buildCommentForm(FundInitiative $initiative) {
    $viewer = $this->getViewer();

    $is_serious = PhabricatorEnv::getEnvConfig('phabricator.serious-business');

    $add_comment_header = $is_serious
      ? pht('Add Comment')
      : pht('Add Liquidity');

    $draft = PhabricatorDraft::newFromUserAndKey(
      $viewer, $initiative->getPHID());

    return id(new PhabricatorApplicationTransactionCommentView())
      ->setUser($viewer)
      ->setObjectPHID($initiative->getPHID())
      ->setDraft($draft)
      ->setHeaderText($add_comment_header)
      ->setAction(
        $this->getApplicationURI('/comment/'.$initiative->getID().'/'))
      ->setSubmitButtonName(pht('Add Comment'));
  }


}
