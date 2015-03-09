<?php

final class PhortuneChargeSearchEngine
  extends PhabricatorApplicationSearchEngine {

  private $account;

  public function canUseInPanelContext() {
    // These only make sense in an account context.
    return false;
  }

  public function setAccount(PhortuneAccount $account) {
    $this->account = $account;
    return $this;
  }

  public function getAccount() {
    return $this->account;
  }

  public function getResultTypeDescription() {
    return pht('Phortune Charges');
  }

  public function getApplicationClassName() {
    return 'PhabricatorPhortuneApplication';
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new PhortuneChargeQuery());

    $viewer = $this->requireViewer();

    $account = $this->getAccount();
    if ($account) {
      $can_edit = PhabricatorPolicyFilter::hasCapability(
        $viewer,
        $account,
        PhabricatorPolicyCapability::CAN_EDIT);
      if (!$can_edit) {
        throw new Exception(
          pht(
            'You can not query charges for an account you are not '.
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
    $account = $this->getAccount();
    if ($account) {
      return '/phortune/'.$account->getID().'/charge/';
    } else {
      return '/phortune/charge/'.$path;
    }
  }

  protected function getBuiltinQueryNames() {
    $names = array(
      'all' => pht('All Charges'),
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
    array $charges,
    PhabricatorSavedQuery $query) {

    $phids = array();
    foreach ($charges as $charge) {
      $phids[] = $charge->getProviderPHID();
      $phids[] = $charge->getCartPHID();
      $phids[] = $charge->getMerchantPHID();
      $phids[] = $charge->getPaymentMethodPHID();
    }

    return $phids;
  }

  protected function renderResultList(
    array $charges,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($charges, 'PhortuneCharge');

    $viewer = $this->requireViewer();

    $table = id(new PhortuneChargeTableView())
      ->setUser($viewer)
      ->setCharges($charges)
      ->setHandles($handles);

    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Charges'))
      ->appendChild($table);
  }
}
