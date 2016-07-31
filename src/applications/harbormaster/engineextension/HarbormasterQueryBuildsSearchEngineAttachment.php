<?php

final class HarbormasterQueryBuildsSearchEngineAttachment
  extends PhabricatorSearchEngineAttachment {

  public function getAttachmentName() {
    return pht('Harbormaster Query Builds');
  }

  public function getAttachmentDescription() {
    return pht(
      'This attachment exists solely to provide compatibility with the '.
      'message format returned by an outdated API method. It will be '.
      'taken away at some point and you should not rely on these fields '.
      'being available.');
  }

  public function getAttachmentForObject($object, $data, $spec) {
    $status_name = HarbormasterBuildStatus::getBuildStatusName(
      $object->getBuildStatus());
    return array(
      'uri' => PhabricatorEnv::getProductionURI($object->getURI()),
      'name' => $object->getName(),
      'buildStatusName' => $status_name,
    );
  }

}
