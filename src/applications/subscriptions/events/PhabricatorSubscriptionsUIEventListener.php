<?php

final class PhabricatorSubscriptionsUIEventListener
  extends PhabricatorEventListener {

  public function register() {
    $this->listen(PhabricatorEventType::TYPE_UI_DIDRENDERACTIONS);
    $this->listen(PhabricatorEventType::TYPE_UI_WILLRENDERPROPERTIES);
  }

  public function handleEvent(PhutilEvent $event) {
    $object = $event->getValue('object');

    switch ($event->getType()) {
      case PhabricatorEventType::TYPE_UI_DIDRENDERACTIONS:
        $this->handleActionEvent($event);
        break;
      case PhabricatorEventType::TYPE_UI_WILLRENDERPROPERTIES:
        // Hacky solution so that property list view on Diffusion
        // commits shows build status, but not Projects, Subscriptions,
        // or Tokens.
        if ($object instanceof PhabricatorRepositoryCommit) {
          return;
        }
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

    $src_phid = $object->getPHID();
    $subscribed_type = PhabricatorObjectHasSubscriberEdgeType::EDGECONST;
    $muted_type = PhabricatorMutedByEdgeType::EDGECONST;

    $edges = id(new PhabricatorEdgeQuery())
      ->withSourcePHIDs(array($src_phid))
      ->withEdgeTypes(
        array(
          $subscribed_type,
          $muted_type,
        ))
      ->withDestinationPHIDs(array($user_phid))
      ->execute();

    if ($user_phid) {
      $is_subscribed = isset($edges[$src_phid][$subscribed_type][$user_phid]);
      $is_muted = isset($edges[$src_phid][$muted_type][$user_phid]);
    } else {
      $is_subscribed = false;
      $is_muted = false;
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
      if ($is_subscribed) {
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

    $mute_action = id(new PhabricatorActionView())
      ->setWorkflow(true)
      ->setHref('/subscriptions/mute/'.$object->getPHID().'/')
      ->setDisabled(!$user_phid);

    if (!$is_muted) {
      $mute_action
        ->setName(pht('Mute Notifications'))
        ->setIcon('fa-volume-up');
    } else {
      $mute_action
        ->setName(pht('Unmute Notifications'))
        ->setIcon('fa-volume-off')
        ->setColor(PhabricatorActionView::RED);
    }


    $actions = $event->getValue('actions');
    $actions[] = $sub_action;
    $actions[] = $mute_action;
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
