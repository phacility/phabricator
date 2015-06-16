<?php

final class HeraldTranscript extends HeraldDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorDestructibleInterface {

  protected $objectTranscript;
  protected $ruleTranscripts = array();
  protected $conditionTranscripts = array();
  protected $applyTranscripts = array();

  protected $time;
  protected $host;
  protected $duration;

  protected $objectPHID;
  protected $dryRun;
  protected $garbageCollected = 0;

  const TABLE_SAVED_HEADER = 'herald_savedheader';

  public function getXHeraldRulesHeader() {
    $ids = array();
    foreach ($this->applyTranscripts as $xscript) {
      if ($xscript->getApplied()) {
        if ($xscript->getRuleID()) {
          $ids[] = $xscript->getRuleID();
        }
      }
    }
    if (!$ids) {
      return 'none';
    }

    // A rule may have multiple effects, which will cause it to be listed
    // multiple times.
    $ids = array_unique($ids);

    foreach ($ids as $k => $id) {
      $ids[$k] = '<'.$id.'>';
    }

    return implode(', ', $ids);
  }

  public static function saveXHeraldRulesHeader($phid, $header) {

    // Combine any existing header with the new header, listing all rules
    // which have ever triggered for this object.
    $header = self::combineXHeraldRulesHeaders(
      self::loadXHeraldRulesHeader($phid),
      $header);

    queryfx(
      id(new HeraldTranscript())->establishConnection('w'),
      'INSERT INTO %T (phid, header) VALUES (%s, %s)
        ON DUPLICATE KEY UPDATE header = VALUES(header)',
      self::TABLE_SAVED_HEADER,
      $phid,
      $header);

    return $header;
  }

  private static function combineXHeraldRulesHeaders($u, $v) {
    $u = preg_split('/[, ]+/', $u);
    $v = preg_split('/[, ]+/', $v);

    $combined = array_unique(array_filter(array_merge($u, $v)));
    return implode(', ', $combined);
  }

  public static function loadXHeraldRulesHeader($phid) {
    $header = queryfx_one(
      id(new HeraldTranscript())->establishConnection('r'),
      'SELECT * FROM %T WHERE phid = %s',
      self::TABLE_SAVED_HEADER,
      $phid);
    if ($header) {
      return idx($header, 'header');
    }
    return null;
  }


  protected function getConfiguration() {
    // Ugh. Too much of a mess to deal with.
    return array(
      self::CONFIG_AUX_PHID     => true,
      self::CONFIG_TIMESTAMPS   => false,
      self::CONFIG_SERIALIZATION => array(
        'objectTranscript'      => self::SERIALIZATION_PHP,
        'ruleTranscripts'       => self::SERIALIZATION_PHP,
        'conditionTranscripts'  => self::SERIALIZATION_PHP,
        'applyTranscripts'      => self::SERIALIZATION_PHP,
      ),
      self::CONFIG_BINARY => array(
        'objectTranscript'      => true,
        'ruleTranscripts'       => true,
        'conditionTranscripts'  => true,
        'applyTranscripts'      => true,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'time' => 'epoch',
        'host' => 'text255',
        'duration' => 'double',
        'dryRun' => 'bool',
        'garbageCollected' => 'bool',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_phid' => null,
        'phid' => array(
          'columns' => array('phid'),
          'unique' => true,
        ),
        'objectPHID' => array(
          'columns' => array('objectPHID'),
        ),
        'garbageCollected' => array(
          'columns' => array('garbageCollected', 'time'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function __construct() {
    $this->time = time();
    $this->host = php_uname('n');
  }

  public function addApplyTranscript(HeraldApplyTranscript $transcript) {
    $this->applyTranscripts[] = $transcript;
    return $this;
  }

  public function getApplyTranscripts() {
    return nonempty($this->applyTranscripts, array());
  }

  public function setDuration($duration) {
    $this->duration = $duration;
    return $this;
  }

  public function setObjectTranscript(HeraldObjectTranscript $transcript) {
    $this->objectTranscript = $transcript;
    return $this;
  }

  public function getObjectTranscript() {
    return $this->objectTranscript;
  }

  public function addRuleTranscript(HeraldRuleTranscript $transcript) {
    $this->ruleTranscripts[$transcript->getRuleID()] = $transcript;
    return $this;
  }

  public function discardDetails() {
    $this->applyTranscripts = null;
    $this->ruleTranscripts = null;
    $this->objectTranscript = null;
    $this->conditionTranscripts = null;
  }

  public function getRuleTranscripts() {
    return nonempty($this->ruleTranscripts, array());
  }

  public function addConditionTranscript(
    HeraldConditionTranscript $transcript) {
    $rule_id = $transcript->getRuleID();
    $cond_id = $transcript->getConditionID();

    $this->conditionTranscripts[$rule_id][$cond_id] = $transcript;
    return $this;
  }

  public function getConditionTranscriptsForRule($rule_id) {
    return idx($this->conditionTranscripts, $rule_id, array());
  }

  public function getMetadataMap() {
    return array(
      pht('Run At Epoch') => date('F jS, g:i:s A', $this->time),
      pht('Run On Host')  => $this->host,
      pht('Run Duration') => (int)(1000 * $this->duration).' ms',
    );
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID('HLXS');
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
        return PhabricatorPolicies::POLICY_USER;
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }

  public function describeAutomaticCapability($capability) {
    return pht(
      'To view a transcript, you must be able to view the object the '.
      'transcript is about.');
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    $this->openTransaction();
      $this->delete();
    $this->saveTransaction();
  }


}
