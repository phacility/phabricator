<?php

final class PhabricatorPasteEditEngine
  extends PhabricatorApplicationEditEngine {

  protected function newEditableObject() {
    return PhabricatorPaste::initializeNewPaste($this->getViewer());
  }

  protected function newObjectQuery() {
    return id(new PhabricatorPasteQuery())
      ->needRawContent(true);
  }

  protected function getObjectCreateTitleText($object) {
    return pht('Create New Paste');
  }

  protected function getObjectEditTitleText($object) {
    return pht('Edit %s %s', $object->getMonogram(), $object->getTitle());
  }

  protected function getObjectEditShortText($object) {
    return $object->getMonogram();
  }

  protected function getObjectCreateShortText($object) {
    return pht('Create Paste');
  }

  protected function getObjectViewURI($object) {
    return '/P'.$object->getID();
  }

  protected function buildCustomEditFields($object) {
    $langs = array(
      '' => pht('(Detect From Filename in Title)'),
    ) + PhabricatorEnv::getEnvConfig('pygments.dropdown-choices');

    return array(
      id(new PhabricatorTextEditField())
        ->setKey('title')
        ->setLabel(pht('Title'))
        ->setTransactionType(PhabricatorPasteTransaction::TYPE_TITLE)
        ->setValue($object->getTitle()),
      id(new PhabricatorSelectEditField())
        ->setKey('language')
        ->setLabel(pht('Language'))
        ->setAliases(array('lang'))
        ->setTransactionType(PhabricatorPasteTransaction::TYPE_LANGUAGE)
        ->setValue($object->getLanguage())
        ->setOptions($langs),
      id(new PhabricatorSelectEditField())
        ->setKey('status')
        ->setLabel(pht('Status'))
        ->setTransactionType(PhabricatorPasteTransaction::TYPE_STATUS)
        ->setValue($object->getStatus())
        ->setOptions(PhabricatorPaste::getStatusNameMap()),
      id(new PhabricatorTextAreaEditField())
        ->setKey('text')
        ->setLabel(pht('Text'))
        ->setTransactionType(PhabricatorPasteTransaction::TYPE_CONTENT)
        ->setMonospaced(true)
        ->setHeight(AphrontFormTextAreaControl::HEIGHT_VERY_TALL)
        ->setValue($object->getRawContent()),
    );
  }

}
