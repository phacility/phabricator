<?php

final class ConduitResultSearchEngineExtension
  extends PhabricatorSearchEngineExtension {

  const EXTENSIONKEY = 'conduit';

  public function isExtensionEnabled() {
    return true;
  }

  public function getExtensionOrder() {
    return 1500;
  }

  public function getExtensionName() {
    return pht('Support for ConduitResultInterface');
  }

  public function supportsObject($object) {
    return ($object instanceof PhabricatorConduitResultInterface);
  }

  public function getFieldSpecificationsForConduit($object) {
    return $object->getFieldSpecificationsForConduit();
  }

  public function getFieldValuesForConduit($object) {
    return $object->getFieldValuesForConduit();
  }

  public function getSearchAttachments($object) {
    return $object->getConduitSearchAttachments();
  }

}
