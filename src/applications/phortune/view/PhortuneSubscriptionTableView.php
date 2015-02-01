<?php

final class PhortuneSubscriptionTableView extends AphrontView {

  private $subscriptions;
  private $handles;
  private $isMerchantView;

  public function setHandles(array $handles) {
    $this->handles = $handles;
    return $this;
  }

  public function getHandles() {
    return $this->handles;
  }

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

  public function render() {
    $subscriptions = $this->getSubscriptions();
    $handles = $this->getHandles();
    $viewer = $this->getUser();

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
