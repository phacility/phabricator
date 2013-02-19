<?php

final class ManiphestPeopleMenuEventListener extends PhutilEventListener {

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

    $href = '/maniphest/view/action/?users='.$person_phid;
    $name = pht('Tasks');

    $menu->addMenuItemToLabel('activity',
      id(new PhabricatorMenuItemView())
      ->setIsExternal(true)
      ->setHref($href)
      ->setName($name)
      ->setKey($name));

    $event->setValue('menu', $menu);
  }

}
