<?php

final class PhabricatorProjectsSearchEngineExtension
  extends PhabricatorSearchEngineExtension {

  const EXTENSIONKEY = 'projects';

  public function isExtensionEnabled() {
    return PhabricatorApplication::isClassInstalled(
      'PhabricatorProjectApplication');
  }

  public function getExtensionName() {
    return pht('Support for Projects');
  }

  public function getExtensionOrder() {
    return 3000;
  }

  public function supportsObject($object) {
    return ($object instanceof PhabricatorProjectInterface);
  }

  public function applyConstraintsToQuery(
    $object,
    $query,
    PhabricatorSavedQuery $saved,
    array $map) {

    if (!empty($map['projectPHIDs'])) {
      $query->withEdgeLogicConstraints(
        PhabricatorProjectObjectHasProjectEdgeType::EDGECONST,
        $map['projectPHIDs']);
    }
  }

  public function getSearchFields($object) {
    $fields = array();

    $fields[] = id(new PhabricatorProjectSearchField())
      ->setKey('projectPHIDs')
      ->setConduitKey('projects')
      ->setAliases(array('project', 'projects'))
      ->setLabel(pht('Projects'))
      ->setDescription(
        pht('Search for objects associated with given projects.'));

    return $fields;
  }

  public function getSearchAttachments($object) {
    return array(
      id(new PhabricatorProjectsSearchEngineAttachment())
        ->setAttachmentKey('projects'),
    );
  }


}
