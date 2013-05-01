<?php

final class ConpherencePeopleMenuEventListener extends PhutilEventListener {

  public function register() {
    $this->listen(PhabricatorEventType::TYPE_PEOPLE_DIDRENDERMENU);
  }

  public function handleEvent(PhutilEvent $event) {
    switch ($event->getType()) {
      case PhabricatorEventType::TYPE_PEOPLE_DIDRENDERMENU:
        $this->handleMenuEvent($event);
      break;
    }
  }

  private function handleMenuEvent($event) {
    $viewer = $event->getUser();
    $menu = $event->getValue('menu');
    $person = $event->getValue('person');

    $conpherence_uri =
      new PhutilURI('/conpherence/new/?participant='.$person->getPHID());
    $name = pht('Message');

    $menu->addMenuItemBefore('activity',
      id(new PhabricatorMenuItemView())
      ->setIsExternal(true)
      ->setName($name)
      ->setHref($conpherence_uri)
      ->setWorkflow(true)
      ->setKey($name));

    $event->setValue('menu', $menu);
  }

}

