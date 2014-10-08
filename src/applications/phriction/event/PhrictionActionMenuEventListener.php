<?php

final class PhrictionActionMenuEventListener extends PhabricatorEventListener {

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
    if ($object instanceof PhabricatorProject) {
      $actions = $this->buildProjectActions($event);
    }

    $this->addActionMenuItems($event, $actions);
  }

  private function buildProjectActions(PhutilEvent $event) {
    if (!$this->canUseApplication($event->getUser())) {
      return null;
    }

    $project = $event->getValue('object');
    $slug = PhabricatorSlug::normalize($project->getPhrictionSlug());
    $href = '/w/projects/'.$slug;

    return id(new PhabricatorActionView())
      ->setIcon('fa-book')
      ->setName(pht('View Wiki'))
      ->setHref($href);
  }

}
