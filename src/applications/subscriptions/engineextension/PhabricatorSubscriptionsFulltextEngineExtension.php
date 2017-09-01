<?php

final class PhabricatorSubscriptionsFulltextEngineExtension
  extends PhabricatorFulltextEngineExtension {

  const EXTENSIONKEY = 'subscriptions';

  public function getExtensionName() {
    return pht('Subscribers');
  }

  public function shouldEnrichFulltextObject($object) {
    return ($object instanceof PhabricatorSubscribableInterface);
  }

  public function enrichFulltextObject(
    $object,
    PhabricatorSearchAbstractDocument $document) {

    $subscriber_phids = PhabricatorSubscribersQuery::loadSubscribersForPHID(
      $object->getPHID());

    if (!$subscriber_phids) {
      return;
    }

    $handles = id(new PhabricatorHandleQuery())
      ->setViewer($this->getViewer())
      ->withPHIDs($subscriber_phids)
      ->execute();

    foreach ($handles as $phid => $handle) {
      $document->addRelationship(
        PhabricatorSearchRelationship::RELATIONSHIP_SUBSCRIBER,
        $phid,
        $handle->getType(),
        $document->getDocumentModified()); // Bogus timestamp.
    }
  }

}
