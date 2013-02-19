<?php

final class AuditPeopleMenuEventListener extends PhutilEventListener {

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
    $username = phutil_escape_uri($person->getUsername());

    $href = '/audit/view/author/'.$username.'/';
    $name = pht('Commits');

    $menu->addMenuItemToLabel('activity',
      id(new PhabricatorMenuItemView())
      ->setIsExternal(true)
      ->setName($name)
      ->setHref($href)
      ->setKey($name));

    $event->setValue('menu', $menu);
  }

}

