<?php

final class ManiphestHovercardEngineExtension
  extends PhabricatorHovercardEngineExtension {

  const EXTENSIONKEY = 'maniphest';

  public function isExtensionEnabled() {
    return PhabricatorApplication::isClassInstalled(
      'PhabricatorManiphestApplication');
  }

  public function getExtensionName() {
    return pht('Maniphest Tasks');
  }

  public function canRenderObjectHovercard($object) {
    return ($object instanceof ManiphestTask);
  }

  public function renderHovercard(
    PHUIHovercardView $hovercard,
    PhabricatorObjectHandle $handle,
    $task,
    $data) {
    $viewer = $this->getViewer();

    $hovercard
      ->setTitle($task->getMonogram())
      ->setDetail($task->getTitle());

    $owner_phid = $task->getOwnerPHID();
    if ($owner_phid) {
      $owner = $viewer->renderHandle($owner_phid);
    } else {
      $owner = phutil_tag('em', array(), pht('None'));
    }
    $hovercard->addField(pht('Assigned To'), $owner);

    $hovercard->addField(
      pht('Priority'),
      ManiphestTaskPriority::getTaskPriorityName($task->getPriority()));

    $hovercard->addTag(ManiphestView::renderTagForTask($task));
  }

}
