<?php

final class PhabricatorSubscriptionsUIEventListener
  extends PhutilEventListener {

  public function register() {
    $this->listen(PhabricatorEventType::TYPE_UI_DIDRENDERACTIONS);
  }

  public function handleEvent(PhutilEvent $event) {
    switch ($event->getType()) {
      case PhabricatorEventType::TYPE_UI_DIDRENDERACTIONS:
        $this->handleActionEvent($event);
      break;
    }
  }

  private function handleActionEvent($event) {
    $user = $event->getUser();
    $object = $event->getValue('object');

    if (!$object || !$object->getPHID()) {
      // No object, or the object has no PHID yet. No way to subscribe.
      return;
    }

    if (!($object instanceof PhabricatorSubscribableInterface)) {
      // This object isn't subscribable.
      return;
    }

    if ($object->isAutomaticallySubscribed($user->getPHID())) {
      $sub_action = id(new PhabricatorActionView())
        ->setWorkflow(true)
        ->setUser($user)
        ->setDisabled(true)
        ->setRenderAsForm(true)
        ->setHref('/subscriptions/add/'.$object->getPHID().'/')
        ->setName('Automatically Subscribed')
        ->setIcon('subscribe-auto');
    } else {
      $subscribed = false;
      if ($user->isLoggedIn()) {
        $src_phid = $object->getPHID();
        $dst_phid = $user->getPHID();
        $edge_type = PhabricatorEdgeConfig::TYPE_OBJECT_HAS_SUBSCRIBER;

        $edges = id(new PhabricatorEdgeQuery())
          ->withSourcePHIDs(array($src_phid))
          ->withEdgeTypes(array($edge_type))
          ->withDestinationPHIDs(array($user->getPHID()))
          ->execute();
        $subscribed = isset($edges[$src_phid][$edge_type][$dst_phid]);
      }

      if ($subscribed) {
        $sub_action = id(new PhabricatorActionView())
          ->setUser($user)
          ->setWorkflow(true)
          ->setRenderAsForm(true)
          ->setHref('/subscriptions/delete/'.$object->getPHID().'/')
          ->setName('Unsubscribe')
          ->setIcon('subscribe-delete');
      } else {
        $sub_action = id(new PhabricatorActionView())
          ->setUser($user)
          ->setWorkflow(true)
          ->setRenderAsForm(true)
          ->setHref('/subscriptions/add/'.$object->getPHID().'/')
          ->setName('Subscribe')
          ->setIcon('subscribe-add');
      }

      if (!$user->isLoggedIn()) {
        $sub_action->setDisabled(true);
      }
    }

    $actions = $event->getValue('actions');
    $actions[] = $sub_action;
    $event->setValue('actions', $actions);
  }

}
