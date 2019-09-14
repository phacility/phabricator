<?php

final class PhortuneSubscriptionSearchEngine
  extends PhabricatorApplicationSearchEngine {

  private $merchant;
  private $account;

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

  public function getResultTypeDescription() {
    return pht('Phortune Subscriptions');
  }

  public function getApplicationClassName() {
    return 'PhabricatorPhortuneApplication';
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new PhortuneSubscriptionQuery());

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
          pht(
            'You can not query subscriptions for a merchant you do not '.
            'control.'));
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
            'You can not query subscriptions for an account you are not '.
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
      return $merchant->getSubscriptionListURI($path);
    } else if ($account) {
      return $account->getSubscriptionListURI($path);
    } else {
      return '/phortune/subscription/'.$path;
    }
  }

  protected function getBuiltinQueryNames() {
    $names = array(
      'all' => pht('All Subscriptions'),
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

  protected function renderResultList(
    array $subscriptions,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($subscriptions, 'PhortuneSubscription');

    $viewer = $this->requireViewer();

    $table = id(new PhortuneSubscriptionTableView())
      ->setUser($viewer)
      ->setSubscriptions($subscriptions);

    $merchant = $this->getMerchant();
    if ($merchant) {
      $header = pht('Subscriptions for %s', $merchant->getName());
      $table->setIsMerchantView(true);
    } else {
      $header = pht('Your Subscriptions');
    }

    $table->setNotice($header);

    $result = new PhabricatorApplicationSearchResultView();
    $result->setTable($table);

    return $result;
  }
}
