<?php

final class PhabricatorSubscribersQuery extends PhabricatorQuery {

  private $objectPHIDs;
  private $subscriberPHIDs;

  public static function loadSubscribersForPHID($phid) {
    if (!$phid) {
      return array();
    }

    $subscribers = id(new PhabricatorSubscribersQuery())
      ->withObjectPHIDs(array($phid))
      ->execute();
    return $subscribers[$phid];
  }

  public function withObjectPHIDs(array $object_phids) {
    $this->objectPHIDs = $object_phids;
    return $this;
  }

  public function withSubscriberPHIDs(array $subscriber_phids) {
    $this->subscriberPHIDs = $subscriber_phids;
    return $this;
  }

  public function execute() {
    $query = new PhabricatorEdgeQuery();

    $edge_type = PhabricatorObjectHasSubscriberEdgeType::EDGECONST;

    $query->withSourcePHIDs($this->objectPHIDs);
    $query->withEdgeTypes(array($edge_type));

    if ($this->subscriberPHIDs) {
      $query->withDestinationPHIDs($this->subscriberPHIDs);
    }

    $edges = $query->execute();

    $results = array_fill_keys($this->objectPHIDs, array());
    foreach ($edges as $src => $edge_types) {
      foreach ($edge_types[$edge_type] as $dst => $data) {
        $results[$src][] = $dst;
      }
    }

    return $results;
  }
}
