<?php

final class PhabricatorProjectsMailEngineExtension
  extends PhabricatorMailEngineExtension {

  const EXTENSIONKEY = 'projects';

  public function supportsObject($object) {
    return ($object instanceof PhabricatorProjectInterface);
  }

  public function newMailStampTemplates($object) {
    return array(
      id(new PhabricatorPHIDMailStamp())
        ->setKey('tag')
        ->setLabel(pht('Tagged with Project')),
    );
  }

  public function newMailStamps($object, array $xactions) {
    $editor = $this->getEditor();
    $viewer = $this->getViewer();

    $project_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
      $object->getPHID(),
      PhabricatorProjectObjectHasProjectEdgeType::EDGECONST);

    $this->getMailStamp('tag')
      ->setValue($project_phids);
  }

}
