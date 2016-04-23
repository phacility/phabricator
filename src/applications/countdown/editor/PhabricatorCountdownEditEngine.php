<?php

final class PhabricatorCountdownEditEngine
  extends PhabricatorEditEngine {

  const ENGINECONST = 'countdown.countdown';

  public function isEngineConfigurable() {
    return false;
  }

  public function getEngineName() {
    return pht('Countdowns');
  }

  public function getSummaryHeader() {
    return pht('Edit Countdowns');
  }

  public function getSummaryText() {
    return pht('Creates and edits countdowns.');
  }

  public function getEngineApplicationClass() {
    return 'PhabricatorCountdownApplication';
  }

  protected function newEditableObject() {
    return PhabricatorCountdown::initializeNewCountdown(
      $this->getViewer());
  }

  protected function newObjectQuery() {
    return id(new PhabricatorCountdownQuery());
  }

  protected function getObjectCreateTitleText($object) {
    return pht('Create Countdown');
  }

  protected function getObjectCreateButtonText($object) {
    return pht('Create Countdown');
  }

  protected function getObjectEditTitleText($object) {
    return pht('Edit Countdown: %s', $object->getTitle());
  }

  protected function getObjectEditShortText($object) {
    return pht('Edit Countdown');
  }

  protected function getObjectCreateShortText() {
    return pht('Create Countdown');
  }

  protected function getObjectName() {
    return pht('Countdown');
  }

  protected function getCommentViewHeaderText($object) {
    return pht('Last Words');
  }

  protected function getCommentViewButtonText($object) {
    return pht('Contemplate Infinity');
  }

  protected function getObjectViewURI($object) {
    return $object->getURI();
  }

  protected function buildCustomEditFields($object) {
    $epoch_value = $object->getEpoch();
    if ($epoch_value === null) {
      $epoch_value = PhabricatorTime::getNow();
    }

    return array(
      id(new PhabricatorTextEditField())
        ->setKey('name')
        ->setLabel(pht('Name'))
        ->setIsRequired(true)
        ->setTransactionType(PhabricatorCountdownTransaction::TYPE_TITLE)
        ->setDescription(pht('The countdown name.'))
        ->setConduitDescription(pht('Rename the countdown.'))
        ->setConduitTypeDescription(pht('New countdown name.'))
        ->setValue($object->getTitle()),
      id(new PhabricatorEpochEditField())
        ->setKey('epoch')
        ->setLabel(pht('End Date'))
        ->setTransactionType(PhabricatorCountdownTransaction::TYPE_EPOCH)
        ->setDescription(pht('Date when the countdown ends.'))
        ->setConduitDescription(pht('Change the end date of the countdown.'))
        ->setConduitTypeDescription(pht('New countdown end date.'))
        ->setValue($epoch_value),
      id(new PhabricatorRemarkupEditField())
        ->setKey('description')
        ->setLabel(pht('Description'))
        ->setTransactionType(PhabricatorCountdownTransaction::TYPE_DESCRIPTION)
        ->setDescription(pht('Description of the countdown.'))
        ->setConduitDescription(pht('Change the countdown description.'))
        ->setConduitTypeDescription(pht('New description.'))
        ->setValue($object->getDescription()),
    );
  }

}
