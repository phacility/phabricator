<?php

final class HeraldWebhookRequest
  extends HeraldDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorExtendedPolicyInterface {

  protected $webhookPHID;
  protected $objectPHID;
  protected $status;
  protected $properties = array();
  protected $lastRequestResult;
  protected $lastRequestEpoch;

  private $webhook = self::ATTACHABLE;

  const RETRY_NEVER = 'never';
  const RETRY_FOREVER = 'forever';

  const STATUS_QUEUED = 'queued';
  const STATUS_FAILED = 'failed';
  const STATUS_SENT = 'sent';

  const RESULT_NONE = 'none';
  const RESULT_OKAY = 'okay';
  const RESULT_FAIL = 'fail';

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'properties' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'status' => 'text32',
        'lastRequestResult' => 'text32',
        'lastRequestEpoch' => 'epoch',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_ratelimit' => array(
          'columns' => array(
            'webhookPHID',
            'lastRequestResult',
            'lastRequestEpoch',
          ),
        ),
        'key_collect' => array(
          'columns' => array('dateCreated'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function getPHIDType() {
    return HeraldWebhookRequestPHIDType::TYPECONST;
  }

  public static function initializeNewWebhookRequest(HeraldWebhook $hook) {
    return id(new self())
      ->setWebhookPHID($hook->getPHID())
      ->attachWebhook($hook)
      ->setStatus(self::STATUS_QUEUED)
      ->setRetryMode(self::RETRY_NEVER)
      ->setLastRequestResult(self::RESULT_NONE)
      ->setLastRequestEpoch(0);
  }

  public function getWebhook() {
    return $this->assertAttached($this->webhook);
  }

  public function attachWebhook(HeraldWebhook $hook) {
    $this->webhook = $hook;
    return $this;
  }

  protected function setProperty($key, $value) {
    $this->properties[$key] = $value;
    return $this;
  }

  protected function getProperty($key, $default = null) {
    return idx($this->properties, $key, $default);
  }

  public function setRetryMode($mode) {
    return $this->setProperty('retry', $mode);
  }

  public function getRetryMode() {
    return $this->getProperty('retry');
  }

  public function setErrorType($error_type) {
    return $this->setProperty('errorType', $error_type);
  }

  public function getErrorType() {
    return $this->getProperty('errorType');
  }

  public function setErrorCode($error_code) {
    return $this->setProperty('errorCode', $error_code);
  }

  public function getErrorCode() {
    return $this->getProperty('errorCode');
  }

  public function setTransactionPHIDs(array $phids) {
    return $this->setProperty('transactionPHIDs', $phids);
  }

  public function getTransactionPHIDs() {
    return $this->getProperty('transactionPHIDs', array());
  }

  public function setTriggerPHIDs(array $phids) {
    return $this->setProperty('triggerPHIDs', $phids);
  }

  public function getTriggerPHIDs() {
    return $this->getProperty('triggerPHIDs', array());
  }

  public function setIsSilentAction($bool) {
    return $this->setProperty('silent', $bool);
  }

  public function getIsSilentAction() {
    return $this->getProperty('silent', false);
  }

  public function setIsTestAction($bool) {
    return $this->setProperty('test', $bool);
  }

  public function getIsTestAction() {
    return $this->getProperty('test', false);
  }

  public function setIsSecureAction($bool) {
    return $this->setProperty('secure', $bool);
  }

  public function getIsSecureAction() {
    return $this->getProperty('secure', false);
  }

  public function queueCall() {
    PhabricatorWorker::scheduleTask(
      'HeraldWebhookWorker',
      array(
        'webhookRequestPHID' => $this->getPHID(),
      ),
      array(
        'objectPHID' => $this->getPHID(),
      ));

    return $this;
  }

  public function newStatusIcon() {
    switch ($this->getStatus()) {
      case self::STATUS_QUEUED:
        $icon = 'fa-refresh';
        $color = 'blue';
        $tooltip = pht('Queued');
        break;
      case self::STATUS_SENT:
        $icon = 'fa-check';
        $color = 'green';
        $tooltip = pht('Sent');
        break;
      case self::STATUS_FAILED:
      default:
        $icon = 'fa-times';
        $color = 'red';
        $tooltip = pht('Failed');
        break;

    }

    return id(new PHUIIconView())
      ->setIcon($icon, $color)
      ->setTooltip($tooltip);
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return PhabricatorPolicies::getMostOpenPolicy();
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }


/* -(  PhabricatorExtendedPolicyInterface  )--------------------------------- */


  public function getExtendedPolicy($capability, PhabricatorUser $viewer) {
    return array(
      array($this->getWebhook(), PhabricatorPolicyCapability::CAN_VIEW),
    );
  }



}
