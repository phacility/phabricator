<?php

final class ExternalAccountsSearchEngineExtension
  extends PhabricatorSearchEngineExtension {

  const EXTENSIONKEY = 'mozilla.external-accounts';

  public function isExtensionEnabled() {
    return true;
  }

  public function getExtensionName() {
    return pht('External Accounts Custom Extension');
  }

  public function getExtensionOrder() {
    return 9001;
  }

  public function supportsObject($object) {
    return ($object instanceof PhabricatorUser);
  }

  public function getSearchAttachments($object) {
    return array(
      id(new ExternalAccountsSearchEngineAttachment())
        ->setAttachmentKey('external-accounts'),
    );
  }
}
