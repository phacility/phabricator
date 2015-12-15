<?php

final class PhabricatorSubscriptionsSearchEngineAttachment
  extends PhabricatorSearchEngineAttachment {

  public function getAttachmentName() {
    return pht('Subscribers');
  }

  public function getAttachmentDescription() {
    return pht('Get information about subscribers.');
  }

  public function loadAttachmentData(array $objects, $spec) {
    $object_phids = mpull($objects, 'getPHID');
    $edge_type = PhabricatorObjectHasSubscriberEdgeType::EDGECONST;


    $subscribers_query = id(new PhabricatorEdgeQuery())
      ->withSourcePHIDs($object_phids)
      ->withEdgeTypes(array($edge_type));
    $subscribers_query->execute();

    $viewer = $this->getViewer();
    $viewer_phid = $viewer->getPHID();
    if ($viewer) {
      $edges = id(new PhabricatorEdgeQuery())
        ->withSourcePHIDs($object_phids)
        ->withEdgeTypes(array($edge_type))
        ->withDestinationPHIDs(array($viewer_phid))
        ->execute();

      $viewer_map = array();
      foreach ($edges as $object_phid => $types) {
        if ($types[$edge_type]) {
          $viewer_map[$object_phid] = true;
        }
      }
    } else {
      $viewer_map = array();
    }

    return array(
      'subscribers.query' => $subscribers_query,
      'viewer.map' => $viewer_map,
    );
  }

  public function getAttachmentForObject($object, $data, $spec) {
    $subscribers_query = idx($data, 'subscribers.query');
    $viewer_map = idx($data, 'viewer.map');
    $object_phid = $object->getPHID();

    $subscribed_phids = $subscribers_query->getDestinationPHIDs(
      array($object_phid),
      array(PhabricatorObjectHasSubscriberEdgeType::EDGECONST));
    $subscribed_count = count($subscribed_phids);
    if ($subscribed_count > 10) {
      $subscribed_phids = array_slice($subscribed_phids, 0, 10);
    }

    $subscribed_phids = array_values($subscribed_phids);

    $viewer = $this->getViewer();
    $viewer_phid = $viewer->getPHID();

    if (!$viewer_phid) {
      $self_subscribed = false;
    } else if (isset($viewer_map[$object_phid])) {
      $self_subscribed = true;
    } else if ($object->isAutomaticallySubscribed($viewer_phid)) {
      $self_subscribed = true;
    } else {
      $self_subscribed = false;
    }

    return array(
      'subscriberPHIDs' => $subscribed_phids,
      'subscriberCount' => $subscribed_count,
      'viewerIsSubscribed' => $self_subscribed,
    );
  }

}
