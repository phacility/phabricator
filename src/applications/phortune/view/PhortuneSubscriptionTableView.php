<?php

final class PhortuneSubscriptionTableView extends AphrontView {

  private $subscriptions;
  private $handles;

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

  public function render() {
    $subscriptions = $this->getSubscriptions();
    $handles = $this->getHandles();
    $viewer = $this->getUser();

    $rows = array();
    $rowc = array();
    foreach ($subscriptions as $subscription) {
      $subscription_link = $handles[$subscription->getPHID()]->renderLink();
      $rows[] = array(
        $subscription->getID(),
        phabricator_datetime($subscription->getDateCreated(), $viewer),
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          pht('ID'),
          pht('Created'),
        ))
      ->setColumnClasses(
        array(
          '',
          'right',
        ));

    return $table;
  }

}
