<?php

final class DiffusionRepositoryURIsSearchEngineAttachment
  extends PhabricatorSearchEngineAttachment {

  public function getAttachmentName() {
    return pht('Repository URIs');
  }

  public function getAttachmentDescription() {
    return pht('Get a list of associated URIs for each repository.');
  }

  public function willLoadAttachmentData($query, $spec) {
    $query->needURIs(true);
  }

  public function getAttachmentForObject($object, $data, $spec) {
    $uris = array();
    foreach ($object->getURIs() as $uri) {
      $uris[] = array(
        'id' => $uri->getID(),
        'type' => phid_get_type($uri->getPHID()),
        'phid' => $uri->getPHID(),
        'fields' => $uri->getFieldValuesForConduit(),
      );
    }

    return array(
      'uris' => $uris,
    );
  }

}
