<?php

final class PhabricatorProjectHovercardEngineExtension
  extends PhabricatorHovercardEngineExtension {

  const EXTENSIONKEY = 'project.card';

  public function isExtensionEnabled() {
    return true;
  }

  public function getExtensionName() {
    return pht('Project Card');
  }

  public function canRenderObjectHovercard($object) {
    return ($object instanceof PhabricatorProject);
  }

  public function willRenderHovercards(array $objects) {
    $viewer = $this->getViewer();
    $phids = mpull($objects, 'getPHID');

    $projects = id(new PhabricatorProjectQuery())
      ->setViewer($viewer)
      ->withPHIDs($phids)
      ->needImages(true)
      ->execute();
    $projects = mpull($projects, null, 'getPHID');

    return array(
      'projects' => $projects,
    );
  }

  public function renderHovercard(
    PHUIHovercardView $hovercard,
    PhabricatorObjectHandle $handle,
    $object,
    $data) {
    $viewer = $this->getViewer();

    $project = idx($data['projects'], $object->getPHID());
    if (!$project) {
      return;
    }

    $project_card = id(new PhabricatorProjectCardView())
      ->setProject($project)
      ->setViewer($viewer);

    $hovercard->appendChild($project_card);
  }

}
