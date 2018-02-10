<?php

final class HeraldWebhookEditEngine
  extends PhabricatorEditEngine {

  const ENGINECONST = 'herald.webhook';

  public function isEngineConfigurable() {
    return false;
  }

  public function getEngineName() {
    return pht('Webhooks');
  }

  public function getSummaryHeader() {
    return pht('Edit Webhook Configurations');
  }

  public function getSummaryText() {
    return pht('This engine is used to edit webhooks.');
  }

  public function getEngineApplicationClass() {
    return 'PhabricatorHeraldApplication';
  }

  protected function newEditableObject() {
    $viewer = $this->getViewer();
    return HeraldWebhook::initializeNewWebhook($viewer);
  }

  protected function newObjectQuery() {
    return new HeraldWebhookQuery();
  }

  protected function getObjectCreateTitleText($object) {
    return pht('Create Webhook');
  }

  protected function getObjectCreateButtonText($object) {
    return pht('Create Webhook');
  }

  protected function getObjectEditTitleText($object) {
    return pht('Edit Webhook: %s', $object->getName());
  }

  protected function getObjectEditShortText($object) {
    return pht('Edit Webhook');
  }

  protected function getObjectCreateShortText() {
    return pht('Create Webhook');
  }

  protected function getObjectName() {
    return pht('Webhook');
  }

  protected function getEditorURI() {
    return '/herald/webhook/edit/';
  }

  protected function getObjectCreateCancelURI($object) {
    return '/herald/webhook/';
  }

  protected function getObjectViewURI($object) {
    return $object->getURI();
  }

  protected function getCreateNewObjectPolicy() {
    return $this->getApplication()->getPolicy(
      HeraldCreateWebhooksCapability::CAPABILITY);
  }

  protected function buildCustomEditFields($object) {
    return array(
      id(new PhabricatorTextEditField())
        ->setKey('name')
        ->setLabel(pht('Name'))
        ->setDescription(pht('Name of the webhook.'))
        ->setTransactionType(HeraldWebhookNameTransaction::TRANSACTIONTYPE)
        ->setIsRequired(true)
        ->setValue($object->getName()),
      id(new PhabricatorTextEditField())
        ->setKey('uri')
        ->setLabel(pht('URI'))
        ->setDescription(pht('URI for the webhook.'))
        ->setTransactionType(HeraldWebhookURITransaction::TRANSACTIONTYPE)
        ->setIsRequired(true)
        ->setValue($object->getWebhookURI()),
      id(new PhabricatorSelectEditField())
        ->setKey('status')
        ->setLabel(pht('Status'))
        ->setDescription(pht('Status mode for the webhook.'))
        ->setTransactionType(HeraldWebhookStatusTransaction::TRANSACTIONTYPE)
        ->setOptions(HeraldWebhook::getStatusDisplayNameMap())
        ->setValue($object->getStatus()),

    );
  }

}
