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
    $crumbs->addTextCrumb($merchant->getName());

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

    $providers = $this->buildProviderList($merchant);

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
        $providers,
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

  private function buildProviderList(PhortuneMerchant $merchant) {
    $viewer = $this->getRequest()->getUser();
    $id = $merchant->getID();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $merchant,
      PhabricatorPolicyCapability::CAN_EDIT);

    $provider_list = id(new PHUIObjectItemListView())
      ->setNoDataString(pht('This merchant has no payment providers.'));

    $providers = id(new PhortunePaymentProviderConfigQuery())
      ->setViewer($viewer)
      ->withMerchantPHIDs(array($merchant->getPHID()))
      ->execute();
    foreach ($providers as $provider_config) {
      $provider = $provider_config->buildProvider();
      $provider_id = $provider_config->getID();

      $item = id(new PHUIObjectItemView())
        ->setObjectName(pht('Provider %d', $provider_id))
        ->setHeader($provider->getName());

      $item->addAction(
        id(new PHUIListItemView())
          ->setIcon('fa-pencil')
          ->setHref($this->getApplicationURI("/provider/edit/{$provider_id}"))
          ->setWorkflow(!$can_edit)
          ->setDisabled(!$can_edit));

      $provider_list->addItem($item);
    }

    $add_action = id(new PHUIButtonView())
      ->setTag('a')
      ->setHref($this->getApplicationURI('provider/edit/?merchantID='.$id))
      ->setText(pht('Add Payment Provider'))
      ->setDisabled(!$can_edit)
      ->setWorkflow(!$can_edit)
      ->setIcon(id(new PHUIIconView())->setIconFont('fa-plus'));

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Payment Providers'))
      ->addActionLink($add_action);

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->appendChild($provider_list);
  }



}
