<?php

final class PhortuneSubscriptionViewController extends PhortuneController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $subscription = id(new PhortuneSubscriptionQuery())
      ->setViewer($viewer)
      ->withIDs(array($request->getURIData('id')))
      ->needTriggers(true)
      ->executeOne();
    if (!$subscription) {
      return new Aphront404Response();
    }

    $is_merchant = (bool)$request->getURIData('merchantID');

    $title = pht('Subscription: %s', $subscription->getSubscriptionName());

    $header = id(new PHUIHeaderView())
      ->setHeader($subscription->getSubscriptionName());

    $actions = id(new PhabricatorActionListView())
      ->setUser($viewer)
      ->setObjectURI($request->getRequestURI());

    $crumbs = $this->buildApplicationCrumbs();
    if ($is_merchant) {
      $this->addMerchantCrumb($crumbs, $subscription->getMerchant());
    } else {
      $this->addAccountCrumb($crumbs, $subscription->getAccount());
    }
    $crumbs->addTextCrumb(pht('Subscription %d', $subscription->getID()));

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setActionList($actions);

    $next_invoice = $subscription->getTrigger()->getNextEventPrediction();
    $properties->addProperty(
      pht('Next Invoice'),
      phabricator_datetime($next_invoice, $viewer));

    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $object_box,
      ),
      array(
        'title' => $title,
      ));
  }

}
