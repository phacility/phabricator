<?php

final class DiffusionPeopleMenuEventListener extends PhutilEventListener {

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
    $person_phid = $event->getValue('person')->getPHID();

    $href = '/diffusion/lint/?owner[0]='.$person_phid;
    $name = pht('Lint Messages');

    $menu->addMenuItemToLabel('activity',
      id(new PhabricatorMenuItemView())
      ->setIsExternal(true)
      ->setHref($href)
      ->setName($name)
      ->setKey($name));

    $event->setValue('menu', $menu);
  }

}

