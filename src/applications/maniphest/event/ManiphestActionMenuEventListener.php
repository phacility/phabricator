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
      '/maniphest/?statuses[]=%s&assigned=%s#R',
      implode(',', ManiphestTaskStatus::getOpenStatusConstants()),
      $phid);

    return id(new PhabricatorActionView())
      ->setIcon('maniphest-dark')
      ->setIconSheet(PHUIIconView::SPRITE_APPS)
      ->setName(pht('View Tasks'))
      ->setHref($view_uri);
  }

  private function renderProjectItems(PhutilEvent $event) {
    if (!$this->canUseApplication($event->getUser())) {
      return null;
    }

    $project = $event->getValue('object');

    $phid = $project->getPHID();
    $view_uri = '/maniphest/?statuses[]=0&allProjects[]='.$phid.'#R';
    $create_uri = '/maniphest/task/create/?projects='.$phid;

    return array(
      id(new PhabricatorActionView())
        ->setIcon('maniphest-dark')
        ->setIconSheet(PHUIIconView::SPRITE_APPS)
        ->setName(pht('View Tasks'))
        ->setHref($view_uri),
      id(new PhabricatorActionView())
        ->setName(pht("Add Task"))
        ->setIcon('create')
        ->setHref($create_uri),
    );
  }


}
