<?php

final class PhabricatorSubscriptionsMailEngineExtension
  extends PhabricatorMailEngineExtension {

  const EXTENSIONKEY = 'subscriptions';

  public function supportsObject($object) {
    return ($object instanceof PhabricatorSubscribableInterface);
  }

  public function newMailStampTemplates($object) {
    return array(
      id(new PhabricatorPHIDMailStamp())
        ->setKey('subscriber')
        ->setLabel(pht('Subscriber')),
    );
  }

  public function newMailStamps($object, array $xactions) {
    $editor = $this->getEditor();
    $viewer = $this->getViewer();

    $subscriber_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
      $object->getPHID(),
      PhabricatorObjectHasSubscriberEdgeType::EDGECONST);

    $this->getMailStamp('subscriber')
      ->setValue($subscriber_phids);
  }

}
