<?php

final class PhabricatorSubscriptionsUIEventListener
  extends PhabricatorEventListener {

  public function register() {
    $this->listen(PhabricatorEventType::TYPE_UI_DIDRENDERACTIONS);
    $this->listen(PhabricatorEventType::TYPE_UI_WILLRENDERPROPERTIES);
  }

  public function handleEvent(PhutilEvent $event) {
    switch ($event->getType()) {
      case PhabricatorEventType::TYPE_UI_DIDRENDERACTIONS:
        $this->handleActionEvent($event);
        break;
      case PhabricatorEventType::TYPE_UI_WILLRENDERPROPERTIES:
        $this->handlePropertyEvent($event);
        break;
    }
  }

  private function handleActionEvent($event) {
    $user = $event->getUser();
    $user_phid = $user->getPHID();
    $object = $event->getValue('object');

    if (!$object || !$object->getPHID()) {
      // No object, or the object has no PHID yet. No way to subscribe.
      return;
    }

    if (!($object instanceof PhabricatorSubscribableInterface)) {
      // This object isn't subscribable.
      return;
    }

    if (!$object->shouldAllowSubscription($user_phid)) {
      // This object doesn't allow the viewer to subscribe.
      return;
    }

    if ($user_phid && $object->isAutomaticallySubscribed($user_phid)) {
      $sub_action = id(new PhabricatorActionView())
        ->setWorkflow(true)
        ->setDisabled(true)
        ->setRenderAsForm(true)
        ->setHref('/subscriptions/add/'.$object->getPHID().'/')
        ->setName(pht('Automatically Subscribed'))
        ->setIcon('fa-check-circle lightgreytext');
    } else {
      $subscribed = false;
      if ($user->isLoggedIn()) {
        $src_phid = $object->getPHID();
        $edge_type = PhabricatorObjectHasSubscriberEdgeType::EDGECONST;

        $edges = id(new PhabricatorEdgeQuery())
          ->withSourcePHIDs(array($src_phid))
          ->withEdgeTypes(array($edge_type))
          ->withDestinationPHIDs(array($user_phid))
          ->execute();
        $subscribed = isset($edges[$src_phid][$edge_type][$user_phid]);
      }

      if ($subscribed) {
        $sub_action = id(new PhabricatorActionView())
          ->setWorkflow(true)
          ->setRenderAsForm(true)
          ->setHref('/subscriptions/delete/'.$object->getPHID().'/')
          ->setName(pht('Unsubscribe'))
          ->setIcon('fa-minus-circle');
      } else {
        $sub_action = id(new PhabricatorActionView())
          ->setWorkflow(true)
          ->setRenderAsForm(true)
          ->setHref('/subscriptions/add/'.$object->getPHID().'/')
          ->setName(pht('Subscribe'))
          ->setIcon('fa-plus-circle');
      }

      if (!$user->isLoggedIn()) {
        $sub_action->setDisabled(true);
      }
    }

    $actions = $event->getValue('actions');
    $actions[] = $sub_action;
    $event->setValue('actions', $actions);
  }

  private function handlePropertyEvent($event) {
    $user = $event->getUser();
    $object = $event->getValue('object');

    if (!$object || !$object->getPHID()) {
      // No object, or the object has no PHID yet..
      return;
    }

    if (!($object instanceof PhabricatorSubscribableInterface)) {
      // This object isn't subscribable.
      return;
    }

    if (!$object->shouldShowSubscribersProperty()) {
      // This object doesn't render subscribers in its property list.
      return;
    }

    $subscribers = PhabricatorSubscribersQuery::loadSubscribersForPHID(
      $object->getPHID());
    if ($subscribers) {
      $handles = id(new PhabricatorHandleQuery())
        ->setViewer($user)
        ->withPHIDs($subscribers)
        ->execute();
    } else {
      $handles = array();
    }
    $sub_view = id(new SubscriptionListStringBuilder())
      ->setObjectPHID($object->getPHID())
      ->setHandles($handles)
      ->buildPropertyString();

    $view = $event->getValue('view');
    $view->addProperty(pht('Subscribers'), $sub_view);
  }

}
