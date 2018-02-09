<?php

final class HeraldWebhook
  extends HeraldDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorApplicationTransactionInterface,
    PhabricatorDestructibleInterface {

  protected $name;
  protected $webhookURI;
  protected $viewPolicy;
  protected $editPolicy;
  protected $status;
  protected $hmacKey;

  const HOOKSTATUS_FIREHOSE = 'firehose';
  const HOOKSTATUS_ENABLED = 'enabled';
  const HOOKSTATUS_DISABLED = 'disabled';

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'name' => 'text128',
        'webhookURI' => 'text255',
        'status' => 'text32',
        'hmacKey' => 'text32',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_status' => array(
          'columns' => array('status'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function getPHIDType() {
    return HeraldWebhookPHIDType::TYPECONST;
  }

  public static function initializeNewWebhook(PhabricatorUser $viewer) {
    return id(new self())
      ->setStatus(self::HOOKSTATUS_ENABLED)
      ->setViewPolicy(PhabricatorPolicies::getMostOpenPolicy())
      ->setEditPolicy($viewer->getPHID())
      ->setHmacKey(Filesystem::readRandomCharacters(32));
  }

  public function getURI() {
    return '/herald/webhook/view/'.$this->getID().'/';
  }

  public function isDisabled() {
    return ($this->getStatus() === self::HOOKSTATUS_DISABLED);
  }

  public static function getStatusDisplayNameMap() {
    return array(
      self::HOOKSTATUS_FIREHOSE => pht('Firehose'),
      self::HOOKSTATUS_ENABLED => pht('Enabled'),
      self::HOOKSTATUS_DISABLED => pht('Disabled'),
    );
  }

  public function getStatusDisplayName() {
    $status = $this->getStatus();
    return idx($this->getStatusDisplayNameMap(), $status);
  }

  public function getErrorBackoffWindow() {
    return phutil_units('5 minutes in seconds');
  }

  public function getErrorBackoffThreshold() {
    return 10;
  }

  public function isInErrorBackoff(PhabricatorUser $viewer) {
    $backoff_window = $this->getErrorBackoffWindow();
    $backoff_threshold = $this->getErrorBackoffThreshold();

    $now = PhabricatorTime::getNow();

    $window_start = ($now - $backoff_window);

    $requests = id(new HeraldWebhookRequestQuery())
      ->setViewer($viewer)
      ->withWebhookPHIDs(array($this->getPHID()))
      ->withLastRequestEpochBetween($window_start, null)
      ->withLastRequestResults(
        array(
          HeraldWebhookRequest::RESULT_FAIL,
        ))
      ->execute();

    if (count($requests) >= $backoff_threshold) {
      return true;
    }

    return false;
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return $this->getViewPolicy();
      case PhabricatorPolicyCapability::CAN_EDIT:
        return $this->getEditPolicy();
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new HeraldWebhookEditor();
  }

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new HeraldWebhookTransaction();
  }

  public function willRenderTimeline(
    PhabricatorApplicationTransactionView $timeline,
    AphrontRequest $request) {
    return $timeline;
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    while (true) {
      $requests = id(new HeraldWebhookRequestQuery())
        ->setViewer($engine->getViewer())
        ->withWebhookPHIDs(array($this->getPHID()))
        ->setLimit(100)
        ->execute();

      if (!$requests) {
        break;
      }

      foreach ($requests as $request) {
        $request->delete();
      }
    }

    $this->delete();
  }


}
