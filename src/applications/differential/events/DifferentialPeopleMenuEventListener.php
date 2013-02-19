<?php

final class DifferentialPeopleMenuEventListener extends PhutilEventListener {

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
    $username = phutil_escape_uri($person->getUserName());

    $href = '/differential/filter/revisions/'.$username.'/';
    $name = pht('Revisions');

    $menu->addMenuItemToLabel('activity',
      id(new PhabricatorMenuItemView())
      ->setIsExternal(true)
      ->setHref($href)
      ->setName($name)
      ->setKey($name));

    $event->setValue('menu', $menu);
  }

}

