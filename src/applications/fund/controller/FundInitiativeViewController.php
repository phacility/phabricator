<?php

final class FundInitiativeViewController
  extends FundController {

  private $id;

  public function shouldAllowPublic() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $initiative = id(new FundInitiativeQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->id))
      ->executeOne();
    if (!$initiative) {
      return new Aphront404Response();
    }

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($initiative->getMonogram());

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
      ->setObjectName($initiative->getMonogram())
      ->setHeader($initiative->getName())
      ->setUser($viewer)
      ->setPolicyObject($initiative)
      ->setStatus($status_icon, $status_color, $status_name);

    $properties = $this->buildPropertyListView($initiative);
    $actions = $this->buildActionListView($initiative);
    $properties->setActionList($actions);

    $box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->appendChild($properties);


    $timeline = $this->buildTransactionTimeline(
      $initiative,
      new FundInitiativeTransactionQuery());
    $timeline
      ->setShouldTerminate(true);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
        $timeline,
      ),
      array(
        'title' => $title,
        'pageObjects' => array($initiative->getPHID()),
      ));
  }

  private function buildPropertyListView(FundInitiative $initiative) {
    $viewer = $this->getRequest()->getUser();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($initiative);

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

    $view->invokeWillRenderEvent();

    $description = $initiative->getDescription();
    if (strlen($description)) {
      $description = PhabricatorMarkupEngine::renderOneObject(
        id(new PhabricatorMarkupOneOff())->setContent($description),
        'default',
        $viewer);

      $view->addSectionHeader(pht('Description'));
      $view->addTextContent($description);
    }

    $risks = $initiative->getRisks();
    if (strlen($risks)) {
      $risks = PhabricatorMarkupEngine::renderOneObject(
        id(new PhabricatorMarkupOneOff())->setContent($risks),
        'default',
        $viewer);

      $view->addSectionHeader(pht('Risks/Challenges'));
      $view->addTextContent($risks);
    }

    return $view;
  }

  private function buildActionListView(FundInitiative $initiative) {
    $viewer = $this->getRequest()->getUser();
    $id = $initiative->getID();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $initiative,
      PhabricatorPolicyCapability::CAN_EDIT);

    $view = id(new PhabricatorActionListView())
      ->setUser($viewer)
      ->setObject($initiative);

    $view->addAction(
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

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName($close_name)
        ->setIcon($close_icon)
        ->setDisabled(!$can_edit)
        ->setWorkflow(true)
        ->setHref($this->getApplicationURI("/close/{$id}/")));

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Back Initiative'))
        ->setIcon('fa-money')
        ->setDisabled($initiative->isClosed())
        ->setWorkflow(true)
        ->setHref($this->getApplicationURI("/back/{$id}/")));

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('View Backers'))
        ->setIcon('fa-bank')
        ->setHref($this->getApplicationURI("/backers/{$id}/")));

    return $view;
  }

}
