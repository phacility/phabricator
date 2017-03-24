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

  public function isEngineConfigurable() {
    return false;
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
    return pht('Edit Badge: %s', $object->getName());
  }

  protected function getObjectEditShortText($object) {
    return $object->getName();
  }

  protected function getObjectCreateShortText() {
    return pht('Create Badge');
  }

  protected function getObjectName() {
    return pht('Badge');
  }

  protected function getObjectCreateCancelURI($object) {
    return $this->getApplication()->getApplicationURI('/');
  }

  protected function getEditorURI() {
    return $this->getApplication()->getApplicationURI('edit/');
  }

  protected function getCommentViewHeaderText($object) {
    return pht('Render Honors');
  }

  protected function getCommentViewButtonText($object) {
    return pht('Salute');
  }

  protected function getObjectViewURI($object) {
    return $object->getViewURI();
  }

  protected function getCreateNewObjectPolicy() {
    return $this->getApplication()->getPolicy(
      PhabricatorBadgesCreateCapability::CAPABILITY);
  }

  protected function buildCustomEditFields($object) {

    return array(
      id(new PhabricatorTextEditField())
        ->setKey('name')
        ->setLabel(pht('Name'))
        ->setDescription(pht('Badge name.'))
        ->setConduitTypeDescription(pht('New badge name.'))
        ->setTransactionType(
          PhabricatorBadgesBadgeNameTransaction::TRANSACTIONTYPE)
        ->setValue($object->getName())
        ->setIsRequired(true),
      id(new PhabricatorTextEditField())
        ->setKey('flavor')
        ->setLabel(pht('Flavor Text'))
        ->setDescription(pht('Short description of the badge.'))
        ->setConduitTypeDescription(pht('New badge flavor.'))
        ->setValue($object->getFlavor())
        ->setTransactionType(
          PhabricatorBadgesBadgeFlavorTransaction::TRANSACTIONTYPE),
      id(new PhabricatorIconSetEditField())
        ->setKey('icon')
        ->setLabel(pht('Icon'))
        ->setIconSet(new PhabricatorBadgesIconSet())
        ->setTransactionType(
          PhabricatorBadgesBadgeIconTransaction::TRANSACTIONTYPE)
        ->setConduitDescription(pht('Change the badge icon.'))
        ->setConduitTypeDescription(pht('New badge icon.'))
        ->setValue($object->getIcon()),
      id(new PhabricatorSelectEditField())
        ->setKey('quality')
        ->setLabel(pht('Quality'))
        ->setDescription(pht('Color and rarity of the badge.'))
        ->setConduitTypeDescription(pht('New badge quality.'))
        ->setValue($object->getQuality())
        ->setTransactionType(
          PhabricatorBadgesBadgeQualityTransaction::TRANSACTIONTYPE)
        ->setOptions(PhabricatorBadgesQuality::getDropdownQualityMap()),
      id(new PhabricatorRemarkupEditField())
        ->setKey('description')
        ->setLabel(pht('Description'))
        ->setDescription(pht('Badge long description.'))
        ->setConduitTypeDescription(pht('New badge description.'))
        ->setTransactionType(
          PhabricatorBadgesBadgeDescriptionTransaction::TRANSACTIONTYPE)
        ->setValue($object->getDescription()),
      id(new PhabricatorUsersEditField())
        ->setKey('award')
        ->setIsConduitOnly(true)
        ->setDescription(pht('New badge award recipients.'))
        ->setConduitTypeDescription(pht('New badge award recipients.'))
        ->setTransactionType(
          PhabricatorBadgesBadgeAwardTransaction::TRANSACTIONTYPE)
        ->setLabel(pht('Award Recipients')),
      id(new PhabricatorUsersEditField())
        ->setKey('revoke')
        ->setIsConduitOnly(true)
        ->setDescription(pht('Revoke badge award recipients.'))
        ->setConduitTypeDescription(pht('Revoke badge award recipients.'))
        ->setTransactionType(
          PhabricatorBadgesBadgeRevokeTransaction::TRANSACTIONTYPE)
        ->setLabel(pht('Revoke Recipients')),

    );
  }

}
