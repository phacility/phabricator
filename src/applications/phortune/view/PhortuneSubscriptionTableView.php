<?php

final class PhortuneSubscriptionTableView extends AphrontView {

  private $subscriptions;
  private $isMerchantView;
  private $notice;

  public function setSubscriptions(array $subscriptions) {
    $this->subscriptions = $subscriptions;
    return $this;
  }

  public function getSubscriptions() {
    return $this->subscriptions;
  }

  public function setIsMerchantView($is_merchant_view) {
    $this->isMerchantView = $is_merchant_view;
    return $this;
  }

  public function getIsMerchantView() {
    return $this->isMerchantView;
  }

  public function setNotice($notice) {
    $this->notice = $notice;
    return $this;
  }

  public function render() {
    return $this->newTableView();
  }

  public function newTableView() {
    $subscriptions = $this->getSubscriptions();
    $viewer = $this->getViewer();

    $phids = mpull($subscriptions, 'getPHID');
    $handles = $viewer->loadHandles($phids);

    $rows = array();
    $rowc = array();
    foreach ($subscriptions as $subscription) {
      if ($this->getIsMerchantView()) {
        $uri = $subscription->getMerchantURI();
      } else {
        $uri = $subscription->getURI();
      }

      $subscription_link = $handles[$subscription->getPHID()]->renderLink();
      $rows[] = array(
        $subscription->getID(),
        phutil_tag(
          'a',
          array(
            'href' => $uri,
          ),
          $subscription->getSubscriptionFullName()),
        phabricator_datetime($subscription->getDateCreated(), $viewer),
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setNotice($this->notice)
      ->setHeaders(
        array(
          pht('ID'),
          pht('Name'),
          pht('Created'),
        ))
      ->setColumnClasses(
        array(
          '',
          'wide',
          'right',
        ));

    return $table;
  }

}
