<?php

final class PhortuneCartSearchEngine
  extends PhabricatorApplicationSearchEngine {

  private $merchant;
  private $account;
  private $subscription;

  public function canUseInPanelContext() {
    // These only make sense in an account or merchant context.
    return false;
  }

  public function setAccount(PhortuneAccount $account) {
    $this->account = $account;
    return $this;
  }

  public function getAccount() {
    return $this->account;
  }

  public function setMerchant(PhortuneMerchant $merchant) {
    $this->merchant = $merchant;
    return $this;
  }

  public function getMerchant() {
    return $this->merchant;
  }

  public function setSubscription(PhortuneSubscription $subscription) {
    $this->subscription = $subscription;
    return $this;
  }

  public function getSubscription() {
    return $this->subscription;
  }

  public function getResultTypeDescription() {
    return pht('Phortune Orders');
  }

  public function getApplicationClassName() {
    return 'PhabricatorPhortuneApplication';
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new PhortuneCartQuery())
      ->needPurchases(true);

    $viewer = $this->requireViewer();

    $merchant = $this->getMerchant();
    $account = $this->getAccount();
    if ($merchant) {
      $query->withMerchantPHIDs(array($merchant->getPHID()));
    } else if ($account) {
      $query->withAccountPHIDs(array($account->getPHID()));
    } else {
      $accounts = id(new PhortuneAccountQuery())
        ->withMemberPHIDs(array($viewer->getPHID()))
        ->execute();
      if ($accounts) {
        $query->withAccountPHIDs(mpull($accounts, 'getPHID'));
      } else {
        throw new Exception(pht('You have no accounts!'));
      }
    }

    $subscription = $this->getSubscription();
    if ($subscription) {
      $query->withSubscriptionPHIDs(array($subscription->getPHID()));
    }

    if ($saved->getParameter('invoices')) {
      $query->withInvoices(true);
    } else {
      $query->withStatuses(
        array(
          PhortuneCart::STATUS_PURCHASING,
          PhortuneCart::STATUS_CHARGED,
          PhortuneCart::STATUS_HOLD,
          PhortuneCart::STATUS_REVIEW,
          PhortuneCart::STATUS_PURCHASED,
        ));
    }

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved_query) {}

  protected function getURI($path) {
    $merchant = $this->getMerchant();
    $account = $this->getAccount();
    if ($merchant) {
      return $merchant->getOrderListURI($path);
    } else if ($account) {
      return $account->getOrderListURI($path);
    } else {
      return '/phortune/order/'.$path;
    }
  }

  protected function getBuiltinQueryNames() {
    $names = array(
      'all' => pht('Order History'),
      'invoices' => pht('Unpaid Invoices'),
    );

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {

    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'all':
        return $query;
      case 'invoices':
        return $query->setParameter('invoices', true);
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  protected function getRequiredHandlePHIDsForResultList(
    array $carts,
    PhabricatorSavedQuery $query) {
    $phids = array();
    foreach ($carts as $cart) {
      $phids[] = $cart->getPHID();
      $phids[] = $cart->getMerchantPHID();
      $phids[] = $cart->getAuthorPHID();
    }
    return $phids;
  }

  protected function renderResultList(
    array $carts,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($carts, 'PhortuneCart');

    $viewer = $this->requireViewer();

    $rows = array();
    foreach ($carts as $cart) {
      $merchant = $cart->getMerchant();

      if ($this->getMerchant()) {
        $href = $this->getApplicationURI(
          'merchant/'.$merchant->getID().'/cart/'.$cart->getID().'/');
      } else {
        $href = $cart->getDetailURI();
      }

      $rows[] = array(
        $cart->getID(),
        $handles[$cart->getPHID()]->renderLink(),
        $handles[$merchant->getPHID()]->renderLink(),
        $handles[$cart->getAuthorPHID()]->renderLink(),
        $cart->getTotalPriceAsCurrency()->formatForDisplay(),
        PhortuneCart::getNameForStatus($cart->getStatus()),
        phabricator_datetime($cart->getDateModified(), $viewer),
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setNoDataString(pht('No orders match the query.'))
      ->setHeaders(
        array(
          pht('ID'),
          pht('Order'),
          pht('Merchant'),
          pht('Authorized By'),
          pht('Amount'),
          pht('Status'),
          pht('Updated'),
        ))
      ->setColumnClasses(
        array(
          '',
          'pri',
          '',
          '',
          'wide right',
          '',
          'right',
        ));

    $merchant = $this->getMerchant();
    if ($merchant) {
      $notice = pht('Orders for %s', $merchant->getName());
    } else {
      $notice = pht('Your Orders');
    }
    $table->setNotice($notice);

    $result = new PhabricatorApplicationSearchResultView();
    $result->setTable($table);

    return $result;
  }
}
