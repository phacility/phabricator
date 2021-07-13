<?php

final class MozillaSearchEngineExtension
  extends PhabricatorSearchEngineExtension {

  const EXTENSIONKEY = 'mozilla.reviewers-extra';

  public function isExtensionEnabled() {
    return true;
  }

  public function getExtensionName() {
    return pht('Reviewers Extra Data Extension');
  }

  public function getExtensionOrder() {
    return 9001;
  }

  public function supportsObject($object) {
    return ($object instanceof DifferentialRevision);
  }

  public function getSearchAttachments($object) {
    return array(
      id(new MozillaExtraReviewerDataSearchEngineAttachment())
        ->setAttachmentKey('reviewers-extra'),
    );
  }
}
