<?php

final class PhabricatorRolesAncestorsSearchEngineAttachment
  extends PhabricatorSearchEngineAttachment {

  public function getAttachmentName() {
    return pht('Role Ancestors');
  }

  public function getAttachmentDescription() {
    return pht('Get the full ancestor list for each role.');
  }

  public function getAttachmentForObject($object, $data, $spec) {
    $ancestors = $object->getAncestorRoles();

    // Order ancestors by depth, ascending.
    $ancestors = array_reverse($ancestors);

    $results = array();
    foreach ($ancestors as $ancestor) {
      $results[] = $ancestor->getRefForConduit();
    }

    return array(
      'ancestors' => $results,
    );
  }

}
