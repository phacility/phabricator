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
    require_celerity_resource('phui-workcard-view-css');

    $id = $task->getID();
    $task = id(new ManiphestTaskQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->needProjectPHIDs(true)
      ->executeOne();

    $phids = array();
    $owner_phid = $task->getOwnerPHID();
    if ($owner_phid) {
      $phids[$owner_phid] = $owner_phid;
    }
    foreach ($task->getProjectPHIDs() as $phid) {
      $phids[$phid] = $phid;
    }

    $handles = $viewer->loadHandles($phids);
    $handles = iterator_to_array($handles);

    $card = id(new ProjectBoardTaskCard())
      ->setViewer($viewer)
      ->setTask($task);

    $owner_phid = $task->getOwnerPHID();
    if ($owner_phid) {
      $owner_handle = $handles[$owner_phid];
      $card->setOwner($owner_handle);
    }

    $project_phids = $task->getProjectPHIDs();
    $project_handles = array_select_keys($handles, $project_phids);
    if ($project_handles) {
      $card->setProjectHandles($project_handles);
    }

    $item = $card->getItem();
    $card = id(new PHUIObjectItemListView())
      ->setFlush(true)
      ->setItemClass('phui-workcard')
      ->addClass('hovercard-task-view')
      ->addItem($item);
    $hovercard->appendChild($card);

  }

}
