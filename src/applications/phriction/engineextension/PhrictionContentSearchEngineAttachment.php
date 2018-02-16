<?php

final class PhrictionContentSearchEngineAttachment
  extends PhabricatorSearchEngineAttachment {

  public function getAttachmentName() {
    return pht('Document Content');
  }

  public function getAttachmentDescription() {
    return pht('Get the content of documents or document histories.');
  }

  public function getAttachmentForObject($object, $data, $spec) {
    if ($object instanceof PhrictionDocument) {
      $content = $object->getContent();
    } else {
      $content = $object;
    }

    return array(
      'title' => $content->getTitle(),
      'path' => $content->getSlug(),
      'authorPHID' => $content->getAuthorPHID(),
      'content' => array(
        'raw' => $content->getContent(),
      ),
    );
  }

}
