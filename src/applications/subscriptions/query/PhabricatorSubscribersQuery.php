<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

final class PhabricatorSubscribersQuery extends PhabricatorQuery {

  private $objectPHIDs;
  private $subscriberPHIDs;

  public static function loadSubscribersForPHID($phid) {
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

    $edge_type = PhabricatorEdgeConfig::TYPE_OBJECT_HAS_SUBSCRIBER;

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
