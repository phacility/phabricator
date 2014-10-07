<?php

final class PhortuneMerchantViewController
  extends PhortuneMerchantController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $merchant = id(new PhortuneMerchantQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->id))
      ->executeOne();
    if (!$merchant) {
      return new Aphront404Response();
    }

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Merchant %d', $merchant->getID()));

    $title = pht(
      'Merchant %d %s',
      $merchant->getID(),
      $merchant->getName());

    $header = id(new PHUIHeaderView())
      ->setObjectName(pht('Merchant %d', $merchant->getID()))
      ->setHeader($merchant->getName())
      ->setUser($viewer)
      ->setPolicyObject($merchant);

    $properties = $this->buildPropertyListView($merchant);
    $actions = $this->buildActionListView($merchant);
    $properties->setActionList($actions);

    $box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->appendChild($properties);

    $xactions = id(new PhortuneMerchantTransactionQuery())
      ->setViewer($viewer)
      ->withObjectPHIDs(array($merchant->getPHID()))
      ->execute();

    $timeline = id(new PhabricatorApplicationTransactionView())
      ->setUser($viewer)
      ->setObjectPHID($merchant->getPHID())
      ->setTransactions($xactions);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
        $timeline,
      ),
      array(
        'title' => $title,
      ));
  }

  private function buildPropertyListView(PhortuneMerchant $merchant) {
    $viewer = $this->getRequest()->getUser();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($merchant);

    return $view;
  }

  private function buildActionListView(PhortuneMerchant $merchant) {
    $viewer = $this->getRequest()->getUser();
    $id = $merchant->getID();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $merchant,
      PhabricatorPolicyCapability::CAN_EDIT);

    $view = id(new PhabricatorActionListView())
      ->setUser($viewer)
      ->setObject($merchant);

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Merchant'))
        ->setIcon('fa-pencil')
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit)
        ->setHref($this->getApplicationURI("merchant/edit/{$id}/")));

    return $view;
  }

}
