<?php

final class PhabricatorOwnersPathsSearchEngineAttachment
  extends PhabricatorSearchEngineAttachment {

  public function getAttachmentName() {
    return pht('Included Paths');
  }

  public function getAttachmentDescription() {
    return pht('Get the paths for each package.');
  }

  public function willLoadAttachmentData($query, $spec) {
    $query->needPaths(true);
  }

  public function getAttachmentForObject($object, $data, $spec) {
    $paths = $object->getPaths();

    $list = array();
    foreach ($paths as $path) {
      $list[] = array(
        'repositoryPHID' => $path->getRepositoryPHID(),
        'path' => $path->getPathDisplay(),
        'excluded' => (bool)$path->getExcluded(),
      );
    }

    return array(
      'paths' => $list,
    );
  }

}
