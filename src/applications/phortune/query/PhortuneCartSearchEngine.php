<?php

final class PhortuneCartSearchEngine
  extends PhabricatorApplicationSearchEngine {

  private $merchant;
  private $account;

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

  public function getResultTypeDescription() {
    return pht('Phortune Orders');
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new PhortuneCartQuery())
      ->needPurchases(true)
      ->withStatuses(
        array(
          PhortuneCart::STATUS_PURCHASING,
          PhortuneCart::STATUS_CHARGED,
          PhortuneCart::STATUS_HOLD,
          PhortuneCart::STATUS_REVIEW,
          PhortuneCart::STATUS_PURCHASED,
        ));

    $viewer = $this->requireViewer();

    $merchant = $this->getMerchant();
    $account = $this->getAccount();
    if ($merchant) {
      $can_edit = PhabricatorPolicyFilter::hasCapability(
        $viewer,
        $merchant,
        PhabricatorPolicyCapability::CAN_EDIT);
      if (!$can_edit) {
        throw new Exception(
          pht('You can not query orders for a merchant you do not control.'));
      }
      $query->withMerchantPHIDs(array($merchant->getPHID()));
    } else if ($account) {
      $can_edit = PhabricatorPolicyFilter::hasCapability(
        $viewer,
        $account,
        PhabricatorPolicyCapability::CAN_EDIT);
      if (!$can_edit) {
        throw new Exception(
          pht(
            'You can not query orders for an account you are not '.
            'a member of.'));
      }
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

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved_query) {}

  protected function getURI($path) {
    $merchant = $this->getMerchant();
    $account = $this->getAccount();
    if ($merchant) {
      return '/phortune/merchant/'.$merchant->getID().'/order/'.$path;
    } else if ($account) {
      return '/phortune/'.$account->getID().'/order/';
    } else {
      return '/phortune/order/'.$path;
    }
  }

  public function getBuiltinQueryNames() {
    $names = array(
      'all' => pht('All Orders'),
    );

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {

    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'all':
        return $query;
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
      $header = pht('Orders for %s', $merchant->getName());
    } else {
      $header = pht('Your Orders');
    }

    return id(new PHUIObjectBoxView())
      ->setHeaderText($header)
      ->appendChild($table);
  }
}
