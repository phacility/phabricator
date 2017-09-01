<?php

final class PhabricatorCustomFieldFulltextEngineExtension
  extends PhabricatorFulltextEngineExtension {

  const EXTENSIONKEY = 'customfield.fields';

  public function getExtensionName() {
    return pht('Custom Fields');
  }

  public function shouldEnrichFulltextObject($object) {
    return ($object instanceof PhabricatorCustomFieldInterface);
  }

  public function enrichFulltextObject(
    $object,
    PhabricatorSearchAbstractDocument $document) {

    // Rebuild the ApplicationSearch indexes. These are internal and not part
    // of the fulltext search, but putting them in this workflow allows users
    // to use the same tools to rebuild the indexes, which is easy to
    // understand.

    $field_list = PhabricatorCustomField::getObjectFields(
      $object,
      PhabricatorCustomField::ROLE_DEFAULT);

    $field_list->setViewer($this->getViewer());
    $field_list->readFieldsFromStorage($object);

    // Rebuild ApplicationSearch indexes.
    $field_list->rebuildIndexes($object);

    // Rebuild global search indexes.
    $field_list->updateAbstractDocument($document);
  }

}
