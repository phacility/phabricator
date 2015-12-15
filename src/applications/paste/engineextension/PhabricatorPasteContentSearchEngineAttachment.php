<?php

final class PhabricatorPasteContentSearchEngineAttachment
  extends PhabricatorSearchEngineAttachment {

  public function getAttachmentName() {
    return pht('Paste Content');
  }

  public function getAttachmentDescription() {
    return pht('Get the full content for each paste.');
  }

  public function willLoadAttachmentData($query, $spec) {
    $query->needRawContent(true);
  }

  public function getAttachmentForObject($object, $data, $spec) {
    return array(
      'content' => $object->getRawContent(),
    );
  }

}
