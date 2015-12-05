<?php

final class PhabricatorSubscriptionsEditEngineExtension
  extends PhabricatorEditEngineExtension {

  const EXTENSIONKEY = 'subscriptions.subscribers';

  public function getExtensionPriority() {
    return 750;
  }

  public function isExtensionEnabled() {
    return true;
  }

  public function getExtensionName() {
    return pht('Subscriptions');
  }

  public function supportsObject(
    PhabricatorEditEngine $engine,
    PhabricatorApplicationTransactionInterface $object) {
    return ($object instanceof PhabricatorSubscribableInterface);
  }

  public function buildCustomEditFields(
    PhabricatorEditEngine $engine,
    PhabricatorApplicationTransactionInterface $object) {

    $subscribers_type = PhabricatorTransactions::TYPE_SUBSCRIBERS;

    $object_phid = $object->getPHID();
    if ($object_phid) {
      $sub_phids = PhabricatorSubscribersQuery::loadSubscribersForPHID(
        $object_phid);
    } else {
      // TODO: Allow applications to provide default subscribers? Maniphest
      // does this at a minimum.
      $sub_phids = array();
    }

    $subscribers_field = id(new PhabricatorSubscribersEditField())
      ->setKey('subscriberPHIDs')
      ->setLabel(pht('Subscribers'))
      ->setEditTypeKey('subscribers')
      ->setDescription(pht('Manage subscribers.'))
      ->setAliases(array('subscriber', 'subscribers'))
      ->setUseEdgeTransactions(true)
      ->setEdgeTransactionDescriptions(
        pht('Add subscribers.'),
        pht('Remove subscribers.'),
        pht('Set subscribers, overwriting current value.'))
      ->setCommentActionLabel(pht('Add Subscribers'))
      ->setTransactionType($subscribers_type)
      ->setValue($sub_phids);

    return array(
      $subscribers_field,
    );
  }

}
