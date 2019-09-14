<?php

final class PhortuneAccountPaymentMethodViewController
  extends PhortuneAccountController {

  protected function shouldRequireAccountEditCapability() {
    return false;
  }

  protected function handleAccountRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $account = $this->getAccount();

    $method = id(new PhortunePaymentMethodQuery())
      ->setViewer($viewer)
      ->withAccountPHIDs(array($account->getPHID()))
      ->withIDs(array($request->getURIData('id')))
      ->withStatuses(
        array(
          PhortunePaymentMethod::STATUS_ACTIVE,
        ))
      ->executeOne();
    if (!$method) {
      return new Aphront404Response();
    }

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb(pht('Payment Methods'), $account->getPaymentMethodsURI())
      ->addTextCrumb($method->getObjectName())
      ->setBorder(true);

    $header = id(new PHUIHeaderView())
      ->setHeader($method->getFullDisplayName());

    $details = $this->newDetailsView($method);

    $timeline = $this->buildTransactionTimeline(
      $method,
      new PhortunePaymentMethodTransactionQuery());
    $timeline->setShouldTerminate(true);

    $autopay = $this->newAutopayView($method);

    $curtain = $this->buildCurtainView($method);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
     ->setCurtain($curtain)
      ->setMainColumn(
        array(
          $details,
          $autopay,
          $timeline,
        ));

    return $this->newPage()
     ->setTitle($method->getObjectName())
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

  private function buildCurtainView(PhortunePaymentMethod $method) {
    $viewer = $this->getViewer();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $method,
      PhabricatorPolicyCapability::CAN_EDIT);

    $edit_uri = $this->getApplicationURI(
      urisprintf(
        'card/%d/edit/',
        $method->getID()));

    $remove_uri = $this->getApplicationURI(
      urisprintf(
        'card/%d/disable/',
        $method->getID()));

    $curtain = $this->newCurtainView($method);

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Payment Method'))
        ->setIcon('fa-pencil')
        ->setHref($edit_uri)
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Remove Payment Method'))
        ->setIcon('fa-times')
        ->setHref($remove_uri)
        ->setDisabled(!$can_edit)
        ->setWorkflow(true));

    return $curtain;
  }

  private function newDetailsView(PhortunePaymentMethod $method) {
    $viewer = $this->getViewer();

    $merchant_phid = $method->getMerchantPHID();
    $handles = $viewer->loadHandles(
      array(
        $merchant_phid,
      ));

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer);

    if (strlen($method->getName())) {
      $view->addProperty(pht('Name'), $method->getDisplayName());
    }

    $view->addProperty(pht('Summary'), $method->getSummary());
    $view->addProperty(pht('Expires'), $method->getDisplayExpires());

    $view->addProperty(
      pht('Merchant'),
      $handles[$merchant_phid]->renderLink());

    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Payment Method Details'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->addPropertyList($view);
  }

  private function newAutopayView(PhortunePaymentMethod $method) {
    $viewer = $this->getViewer();

    $subscriptions = id(new PhortuneSubscriptionQuery())
      ->setViewer($viewer)
      ->withPaymentMethodPHIDs(array($method->getPHID()))
      ->execute();

    $table = id(new PhortuneSubscriptionTableView())
      ->setViewer($viewer)
      ->setSubscriptions($subscriptions)
      ->newTableView();

    $table->setNoDataString(
      pht(
        'This payment method is not the default payment method for '.
        'any subscriptions.'));

    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Autopay Subscriptions'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setTable($table);
  }

}
