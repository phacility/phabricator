<?php

final class PhabricatorBadgesEditEngine
  extends PhabricatorEditEngine {

  const ENGINECONST = 'badges.badge';

  public function getEngineName() {
    return pht('Badges');
  }

  public function getEngineApplicationClass() {
    return 'PhabricatorBadgesApplication';
  }

  public function getSummaryHeader() {
    return pht('Configure Badges Forms');
  }

  public function getSummaryText() {
    return pht('Configure creation and editing forms in Badges.');
  }

  protected function newEditableObject() {
    return PhabricatorBadgesBadge::initializeNewBadge($this->getViewer());
  }

  protected function newObjectQuery() {
    return new PhabricatorBadgesQuery();
  }

  protected function getObjectCreateTitleText($object) {
    return pht('Create New Badge');
  }

  protected function getObjectEditTitleText($object) {
    return pht('Edit %s', $object->getName());
  }

  protected function getObjectEditShortText($object) {
    return $object->getName();
  }

  protected function getObjectCreateShortText() {
    return pht('Create Badge');
  }

  protected function getCommentViewHeaderText($object) {
    return pht('Add Comment');
  }

  protected function getCommentViewButtonText($object) {
    return pht('Submit');
  }

  protected function getObjectViewURI($object) {
    return $object->getViewURI();
  }

  protected function buildCustomEditFields($object) {

    return array(
      id(new PhabricatorTextEditField())
        ->setKey('name')
        ->setLabel(pht('Name'))
        ->setDescription(pht('Badge name.'))
        ->setTransactionType(PhabricatorBadgesTransaction::TYPE_NAME)
        ->setValue($object->getName()),
      id(new PhabricatorTextEditField())
        ->setKey('flavor')
        ->setLabel(pht('Flavor text'))
        ->setDescription(pht('Short description of the badge.'))
        ->setValue($object->getFlavor())
        ->setTransactionType(PhabricatorBadgesTransaction::TYPE_FLAVOR),
      id(new PhabricatorIconSetEditField())
        ->setKey('icon')
        ->setLabel(pht('Icon'))
        ->setIconSet(new PhabricatorBadgesIconSet())
        ->setTransactionType(PhabricatorBadgesTransaction::TYPE_ICON)
        ->setConduitDescription(pht('Change the badge icon.'))
        ->setConduitTypeDescription(pht('New badge icon.'))
        ->setValue($object->getIcon()),
      id(new PhabricatorSelectEditField())
        ->setKey('quality')
        ->setLabel(pht('Quality'))
        ->setValue($object->getQuality())
        ->setTransactionType(PhabricatorBadgesTransaction::TYPE_QUALITY)
        ->setOptions($object->getQualityNameMap()),
      id(new PhabricatorRemarkupEditField())
        ->setKey('description')
        ->setLabel(pht('Description'))
        ->setDescription(pht('Badge long description.'))
        ->setTransactionType(PhabricatorBadgesTransaction::TYPE_DESCRIPTION)
        ->setValue($object->getDescription()),
    );
  }

}
