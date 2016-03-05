<?php

final class PhabricatorSubscriptionsCurtainExtension
  extends PHUICurtainExtension {

  const EXTENSIONKEY = 'subscriptions.subscribers';

  public function shouldEnableForObject($object) {
    return ($object instanceof PhabricatorSubscribableInterface);
  }

  public function getExtensionApplication() {
    return new PhabricatorSubscriptionsApplication();
  }

  public function buildCurtainPanel($object) {
    $viewer = $this->getViewer();
    $object_phid = $object->getPHID();

    $subscriber_phids = PhabricatorSubscribersQuery::loadSubscribersForPHID(
      $object_phid);

    $handles = $viewer->loadHandles($subscriber_phids);

    // TODO: This class can't accept a HandleList yet.
    $handles = iterator_to_array($handles);

    $susbscribers_view = id(new SubscriptionListStringBuilder())
      ->setObjectPHID($object_phid)
      ->setHandles($handles)
      ->buildPropertyString();

    return $this->newPanel()
      ->setHeaderText(pht('Subscribers'))
      ->setOrder(20000)
      ->appendChild($susbscribers_view);
  }

}
