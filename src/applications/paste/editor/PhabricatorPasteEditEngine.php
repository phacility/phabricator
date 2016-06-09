<?php

final class PhabricatorPasteEditEngine
  extends PhabricatorEditEngine {

  const ENGINECONST = 'paste.paste';

  public function getEngineName() {
    return pht('Pastes');
  }

  public function getSummaryHeader() {
    return pht('Configure Paste Forms');
  }

  public function getSummaryText() {
    return pht('Configure creation and editing forms in Paste.');
  }

  public function getEngineApplicationClass() {
    return 'PhabricatorPasteApplication';
  }

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
    return pht('Edit Paste: %s', $object->getTitle());
  }

  protected function getObjectEditShortText($object) {
    return $object->getMonogram();
  }

  protected function getObjectCreateShortText() {
    return pht('Create Paste');
  }

  protected function getObjectName() {
    return pht('Paste');
  }

  protected function getCommentViewHeaderText($object) {
    return pht('Eat Paste');
  }

  protected function getCommentViewButtonText($object) {
    return pht('Nom Nom Nom Nom Nom');
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
        ->setTransactionType(PhabricatorPasteTitleTransaction::TRANSACTIONTYPE)
        ->setDescription(pht('The title of the paste.'))
        ->setConduitDescription(pht('Retitle the paste.'))
        ->setConduitTypeDescription(pht('New paste title.'))
        ->setValue($object->getTitle()),
      id(new PhabricatorSelectEditField())
        ->setKey('language')
        ->setLabel(pht('Language'))
        ->setTransactionType(
          PhabricatorPasteLanguageTransaction::TRANSACTIONTYPE)
        ->setAliases(array('lang'))
        ->setIsCopyable(true)
        ->setOptions($langs)
        ->setDescription(
          pht(
            'Language used for syntax highlighting. By default, inferred '.
            'from the title.'))
        ->setConduitDescription(
          pht('Change language used for syntax highlighting.'))
        ->setConduitTypeDescription(pht('New highlighting language.'))
        ->setValue($object->getLanguage()),
      id(new PhabricatorTextAreaEditField())
        ->setKey('text')
        ->setLabel(pht('Text'))
        ->setTransactionType(
          PhabricatorPasteContentTransaction::TRANSACTIONTYPE)
        ->setMonospaced(true)
        ->setHeight(AphrontFormTextAreaControl::HEIGHT_VERY_TALL)
        ->setDescription(pht('The main body text of the paste.'))
        ->setConduitDescription(pht('Change the paste content.'))
        ->setConduitTypeDescription(pht('New body content.'))
        ->setValue($object->getRawContent()),
      id(new PhabricatorSelectEditField())
        ->setKey('status')
        ->setLabel(pht('Status'))
        ->setTransactionType(
          PhabricatorPasteStatusTransaction::TRANSACTIONTYPE)
        ->setIsConduitOnly(true)
        ->setOptions(PhabricatorPaste::getStatusNameMap())
        ->setDescription(pht('Active or archived status.'))
        ->setConduitDescription(pht('Active or archive the paste.'))
        ->setConduitTypeDescription(pht('New paste status constant.'))
        ->setValue($object->getStatus()),
    );
  }

}
