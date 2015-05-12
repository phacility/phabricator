<?php

final class QuickCloseTaskListener
  extends PhabricatorAutoEventListener {

  public function register() {
    $this->listen(PhabricatorEventType::TYPE_UI_DIDRENDERACTIONS);
  }

  public function handleEvent(PhutilEvent $event) {
    $task = $event->getValue('object');

    if (!($task instanceof ManiphestTask)) {
      return;
    }

    $actions = $event->getValue('actions');

    $close_status = ManiphestTaskStatus::getDefaultClosedStatus();
    $close_name = ManiphestTaskStatus::getTaskStatusName($close_status);

    $uri = id(new PhutilURI('/maniphest/transaction/save/'))
      ->setQueryParam('taskID', $task->getID())
      ->setQueryParam('action', 'status')
      ->setQueryParam('resolution', $close_status);

    $actions[] = id(new PhabricatorActionView())
      ->setIcon('fa-check-square-o')
      ->setRenderAsForm(true)
      ->setDisabled($task->isClosed())
      ->setHref($uri)
      ->setName(pht('Close Task'));

    $event->setValue('actions', $actions);
  }

}
