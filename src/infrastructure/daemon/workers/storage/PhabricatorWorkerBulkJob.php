<?php

/**
 * @task implementation Job Implementation
 */
final class PhabricatorWorkerBulkJob
  extends PhabricatorWorkerDAO
  implements
     PhabricatorPolicyInterface,
     PhabricatorSubscribableInterface,
     PhabricatorApplicationTransactionInterface,
     PhabricatorDestructibleInterface {

  const STATUS_CONFIRM = 'confirm';
  const STATUS_WAITING = 'waiting';
  const STATUS_RUNNING = 'running';
  const STATUS_COMPLETE = 'complete';

  protected $authorPHID;
  protected $jobTypeKey;
  protected $status;
  protected $parameters = array();
  protected $size;

  private $jobImplementation = self::ATTACHABLE;

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'parameters' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'jobTypeKey' => 'text32',
        'status' => 'text32',
        'size' => 'uint32',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_type' => array(
          'columns' => array('jobTypeKey'),
        ),
        'key_author' => array(
          'columns' => array('authorPHID'),
        ),
        'key_status' => array(
          'columns' => array('status'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public static function initializeNewJob(
    PhabricatorUser $actor,
    PhabricatorWorkerBulkJobType $type,
    array $parameters) {

    $job = id(new PhabricatorWorkerBulkJob())
      ->setAuthorPHID($actor->getPHID())
      ->setJobTypeKey($type->getBulkJobTypeKey())
      ->setParameters($parameters)
      ->attachJobImplementation($type);

    $job->setSize($job->computeSize());

    return $job;
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorWorkerBulkJobPHIDType::TYPECONST);
  }

  public function getMonitorURI() {
    return '/daemon/bulk/monitor/'.$this->getID().'/';
  }

  public function getManageURI() {
    return '/daemon/bulk/view/'.$this->getID().'/';
  }

  public function getParameter($key, $default = null) {
    return idx($this->parameters, $key, $default);
  }

  public function setParameter($key, $value) {
    $this->parameters[$key] = $value;
    return $this;
  }

  public function loadTaskStatusCounts() {
    $table = new PhabricatorWorkerBulkTask();
    $conn_r = $table->establishConnection('r');
    $rows = queryfx_all(
      $conn_r,
      'SELECT status, COUNT(*) N FROM %T WHERE bulkJobPHID = %s
        GROUP BY status',
      $table->getTableName(),
      $this->getPHID());

    return ipull($rows, 'N', 'status');
  }

  public function newContentSource() {
    return PhabricatorContentSource::newForSource(
      PhabricatorContentSource::SOURCE_BULK,
      array(
        'jobID' => $this->getID(),
      ));
  }

  public function getStatusIcon() {
    $map = array(
      self::STATUS_CONFIRM => 'fa-question',
      self::STATUS_WAITING => 'fa-clock-o',
      self::STATUS_RUNNING => 'fa-clock-o',
      self::STATUS_COMPLETE => 'fa-check grey',
    );

    return idx($map, $this->getStatus(), 'none');
  }

  public function getStatusName() {
    $map = array(
      self::STATUS_CONFIRM => pht('Confirming'),
      self::STATUS_WAITING => pht('Waiting'),
      self::STATUS_RUNNING => pht('Running'),
      self::STATUS_COMPLETE => pht('Complete'),
    );

    return idx($map, $this->getStatus(), $this->getStatus());
  }


/* -(  Job Implementation  )------------------------------------------------- */


  protected function getJobImplementation() {
    return $this->assertAttached($this->jobImplementation);
  }

  public function attachJobImplementation(PhabricatorWorkerBulkJobType $type) {
    $this->jobImplementation = $type;
    return $this;
  }

  private function computeSize() {
    return $this->getJobImplementation()->getJobSize($this);
  }

  public function getCancelURI() {
    return $this->getJobImplementation()->getCancelURI($this);
  }

  public function getDoneURI() {
    return $this->getJobImplementation()->getDoneURI($this);
  }

  public function getDescriptionForConfirm() {
    return $this->getJobImplementation()->getDescriptionForConfirm($this);
  }

  public function createTasks() {
    return $this->getJobImplementation()->createTasks($this);
  }

  public function runTask(
    PhabricatorUser $actor,
    PhabricatorWorkerBulkTask $task) {
    return $this->getJobImplementation()->runTask($actor, $this, $task);
  }

  public function getJobName() {
    return $this->getJobImplementation()->getJobName($this);
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
        return PhabricatorPolicies::getMostOpenPolicy();
      case PhabricatorPolicyCapability::CAN_EDIT:
        return $this->getAuthorPHID();
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }

  public function describeAutomaticCapability($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_EDIT:
        return pht('Only the owner of a bulk job can edit it.');
      default:
        return null;
    }
  }


/* -(  PhabricatorSubscribableInterface  )----------------------------------- */


  public function isAutomaticallySubscribed($phid) {
    return false;
  }

  public function shouldShowSubscribersProperty() {
    return true;
  }

  public function shouldAllowSubscription($phid) {
    return true;
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new PhabricatorWorkerBulkJobEditor();
  }

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new PhabricatorWorkerBulkJobTransaction();
  }

  public function willRenderTimeline(
    PhabricatorApplicationTransactionView $timeline,
    AphrontRequest $request) {
    return $timeline;
  }

/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    $this->openTransaction();

      // We're only removing the actual task objects. This may leave stranded
      // workers in the queue itself, but they'll just flush out automatically
      // when they can't load bulk job data.

      $task_table = new PhabricatorWorkerBulkTask();
      $conn_w = $task_table->establishConnection('w');
      queryfx(
        $conn_w,
        'DELETE FROM %T WHERE bulkJobPHID = %s',
        $task_table->getPHID(),
        $this->getPHID());

      $this->delete();
    $this->saveTransaction();
  }


}
