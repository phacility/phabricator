<?php

final class ManiphestActionMenuEventListener extends PhabricatorEventListener {

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

  private function handleActionsEvent(PhutilEvent $event) {
    $object = $event->getValue('object');

    $actions = null;
    if ($object instanceof PhabricatorUser) {
      $actions = $this->renderUserItems($event);
    }

    $this->addActionMenuItems($event, $actions);
  }

  private function renderUserItems(PhutilEvent $event) {
    if (!$this->canUseApplication($event->getUser())) {
      return null;
    }

    $user = $event->getValue('object');
    $phid = $user->getPHID();
    $view_uri = sprintf(
      '/maniphest/?statuses=%s&assigned=%s#R',
      implode(',', ManiphestTaskStatus::getOpenStatusConstants()),
      $phid);

    return id(new PhabricatorActionView())
      ->setIcon('fa-anchor')
      ->setName(pht('View Tasks'))
      ->setHref($view_uri);
  }

}
