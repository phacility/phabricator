<?php

final class ConpherencePeopleMenuEventListener extends PhutilEventListener {

  public function register() {
    $this->listen(PhabricatorEventType::TYPE_UI_DIDRENDERACTIONS);
  }

  public function handleEvent(PhutilEvent $event) {
    switch ($event->getType()) {
      case PhabricatorEventType::TYPE_UI_DIDRENDERACTIONS:
        $this->handleActionsEvent($event);
      break;
    }
  }

  private function handleActionsEvent($event) {
    $person = $event->getValue('object');
    if (!($person instanceof PhabricatorUser)) {
      return;
    }

    $href = '/conpherence/new/?participant='.$person->getPHID();

    $actions = $event->getValue('actions');

    $actions[] = id(new PhabricatorActionView())
      ->setIcon('message')
      ->setName(pht('Send Message'))
      ->setWorkflow(true)
      ->setHref($href);

    $event->setValue('actions', $actions);
  }

}

