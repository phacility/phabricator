<?php

final class HeraldWebhook
  extends HeraldDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorApplicationTransactionInterface,
    PhabricatorDestructibleInterface,
    PhabricatorProjectInterface {

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
      ->regenerateHMACKey();
  }

  public function getURI() {
    return '/herald/webhook/view/'.$this->getID().'/';
  }

  public function isDisabled() {
    return ($this->getStatus() === self::HOOKSTATUS_DISABLED);
  }

  public static function getStatusDisplayNameMap() {
    $specs = self::getStatusSpecifications();
    return ipull($specs, 'name', 'key');
  }

  private static function getStatusSpecifications() {
    $specs = array(
      array(
        'key' => self::HOOKSTATUS_FIREHOSE,
        'name' => pht('Firehose'),
        'color' => 'orange',
        'icon' => 'fa-star-o',
      ),
      array(
        'key' => self::HOOKSTATUS_ENABLED,
        'name' => pht('Enabled'),
        'color' => 'bluegrey',
        'icon' => 'fa-check',
      ),
      array(
        'key' => self::HOOKSTATUS_DISABLED,
        'name' => pht('Disabled'),
        'color' => 'dark',
        'icon' => 'fa-ban',
      ),
    );

    return ipull($specs, null, 'key');
  }


  private static function getSpecificationForStatus($status) {
    $specs = self::getStatusSpecifications();

    if (isset($specs[$status])) {
      return $specs[$status];
    }

    return array(
      'key' => $status,
      'name' => pht('Unknown ("%s")', $status),
      'icon' => 'fa-question',
      'color' => 'indigo',
    );
  }

  public static function getDisplayNameForStatus($status) {
    $spec = self::getSpecificationForStatus($status);
    return $spec['name'];
  }

  public static function getIconForStatus($status) {
    $spec = self::getSpecificationForStatus($status);
    return $spec['icon'];
  }

  public static function getColorForStatus($status) {
    $spec = self::getSpecificationForStatus($status);
    return $spec['color'];
  }

  public function getStatusDisplayName() {
    $status = $this->getStatus();
    return self::getDisplayNameForStatus($status);
  }

  public function getStatusIcon() {
    $status = $this->getStatus();
    return self::getIconForStatus($status);
  }

  public function getStatusColor() {
    $status = $this->getStatus();
    return self::getColorForStatus($status);
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

  public function regenerateHMACKey() {
    return $this->setHMACKey(Filesystem::readRandomCharacters(32));
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

  public function getApplicationTransactionTemplate() {
    return new HeraldWebhookTransaction();
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
