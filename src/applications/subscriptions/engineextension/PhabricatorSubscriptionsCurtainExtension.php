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
    $viewer_phid = $viewer->getPHID();
    $object_phid = $object->getPHID();

    $max_handles = 100;
    $max_visible = 8;

    // TODO: We should limit the number of subscriber PHIDs we'll load, so
    // we degrade gracefully when objects have thousands of subscribers.

    $subscriber_phids = PhabricatorSubscribersQuery::loadSubscribersForPHID(
      $object_phid);
    $subscriber_count = count($subscriber_phids);

    $subscriber_phids = $this->sortSubscriberPHIDs(
      $subscriber_phids,
      null);

    // If we have fewer subscribers than the maximum number of handles we're
    // willing to load, load all the handles and then sort the list based on
    // complete handle data.

    // If we have too many PHIDs, we'll skip this step and accept a less
    // useful ordering.
    $handles = null;
    if ($subscriber_count <= $max_handles) {
      $handles = $viewer->loadHandles($subscriber_phids);

      $subscriber_phids = $this->sortSubscriberPHIDs(
        $subscriber_phids,
        $handles);
    }

    // If we have more PHIDs to show than visible slots, slice the list.
    if ($subscriber_count > $max_visible) {
      $visible_phids = array_slice($subscriber_phids, 0, $max_visible - 1);
      $show_all = true;
    } else {
      $visible_phids = $subscriber_phids;
      $show_all = false;
    }

    // If we didn't load handles earlier because we had too many PHIDs,
    // load them now.
    if ($handles === null) {
      $handles = $viewer->loadHandles($visible_phids);
    }

    PhabricatorPolicyFilterSet::loadHandleViewCapabilities(
      $viewer,
      $handles,
      array($object));

    $ref_list = id(new PHUICurtainObjectRefListView())
      ->setViewer($viewer)
      ->setEmptyMessage(pht('None'));

    foreach ($visible_phids as $phid) {
      $handle = $handles[$phid];

      $ref = $ref_list->newObjectRefView()
        ->setHandle($handle);

      if ($phid === $viewer_phid) {
        $ref->setHighlighted(true);
      }

      if ($handle->hasCapabilities()) {
        if (!$handle->hasViewCapability($object)) {
          $ref->setExiled(true);
        }
      }
    }

    if ($show_all) {
      $view_all_uri = urisprintf(
        '/subscriptions/list/%s/',
        $object_phid);

      $ref_list->newTailLink()
        ->setURI($view_all_uri)
        ->setText(pht('View All %d Subscriber(s)', $subscriber_count))
        ->setWorkflow(true);
    }

    return $this->newPanel()
      ->setHeaderText(pht('Subscribers'))
      ->setOrder(20000)
      ->appendChild($ref_list);
  }

  private function sortSubscriberPHIDs(array $subscriber_phids, $handles) {

    // Sort subscriber PHIDs with or without handle data. If we have handles,
    // we can sort results more comprehensively.

    $viewer = $this->getViewer();

    $user_type = PhabricatorPeopleUserPHIDType::TYPECONST;
    $viewer_phid = $viewer->getPHID();

    $type_order_map = array(
      PhabricatorPeopleUserPHIDType::TYPECONST => 0,
      PhabricatorProjectProjectPHIDType::TYPECONST => 1,
      PhabricatorOwnersPackagePHIDType::TYPECONST => 2,
    );
    $default_type_order = count($type_order_map);

    $subscriber_map = array();
    foreach ($subscriber_phids as $subscriber_phid) {
      $is_viewer = ($viewer_phid === $subscriber_phid);

      $subscriber_type = phid_get_type($subscriber_phid);
      $type_order = idx($type_order_map, $subscriber_type, $default_type_order);

      $sort_name = '';
      $is_complete = false;
      if ($handles) {
        if (isset($handles[$subscriber_phid])) {
          $handle = $handles[$subscriber_phid];
          if ($handle->isComplete()) {
            $is_complete = true;

            $sort_name = $handle->getLinkName();
            $sort_name = phutil_utf8_strtolower($sort_name);
          }
        }
      }

      $subscriber_map[$subscriber_phid] = id(new PhutilSortVector())
        ->addInt($is_viewer ? 0 : 1)
        ->addInt($is_complete ? 0 : 1)
        ->addInt($type_order)
        ->addString($sort_name);
    }

    $subscriber_map = msortv($subscriber_map, 'getSelf');

    return array_keys($subscriber_map);
  }

}
