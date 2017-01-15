<?php

final class PhabricatorSlowvotePoll extends PhabricatorSlowvoteDAO
  implements
    PhabricatorApplicationTransactionInterface,
    PhabricatorPolicyInterface,
    PhabricatorSubscribableInterface,
    PhabricatorFlaggableInterface,
    PhabricatorTokenReceiverInterface,
    PhabricatorProjectInterface,
    PhabricatorDestructibleInterface,
    PhabricatorSpacesInterface {

  const RESPONSES_VISIBLE = 0;
  const RESPONSES_VOTERS  = 1;
  const RESPONSES_OWNER   = 2;

  const METHOD_PLURALITY  = 0;
  const METHOD_APPROVAL   = 1;

  protected $question;
  protected $description;
  protected $authorPHID;
  protected $responseVisibility;
  protected $shuffle;
  protected $method;
  protected $mailKey;
  protected $viewPolicy;
  protected $isClosed = 0;
  protected $spacePHID;

  private $options = self::ATTACHABLE;
  private $choices = self::ATTACHABLE;
  private $viewerChoices = self::ATTACHABLE;

  public static function initializeNewPoll(PhabricatorUser $actor) {
    $app = id(new PhabricatorApplicationQuery())
      ->setViewer($actor)
      ->withClasses(array('PhabricatorSlowvoteApplication'))
      ->executeOne();

    $view_policy = $app->getPolicy(
      PhabricatorSlowvoteDefaultViewCapability::CAPABILITY);

    return id(new PhabricatorSlowvotePoll())
      ->setAuthorPHID($actor->getPHID())
      ->setViewPolicy($view_policy)
      ->setSpacePHID($actor->getDefaultSpacePHID());
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'question' => 'text255',
        'responseVisibility' => 'uint32',
        'shuffle' => 'uint32',
        'method' => 'uint32',
        'description' => 'text',
        'isClosed' => 'bool',
        'mailKey' => 'bytes20',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_phid' => null,
        'phid' => array(
          'columns' => array('phid'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorSlowvotePollPHIDType::TYPECONST);
  }

  public function getOptions() {
    return $this->assertAttached($this->options);
  }

  public function attachOptions(array $options) {
    assert_instances_of($options, 'PhabricatorSlowvoteOption');
    $this->options = $options;
    return $this;
  }

  public function getChoices() {
    return $this->assertAttached($this->choices);
  }

  public function attachChoices(array $choices) {
    assert_instances_of($choices, 'PhabricatorSlowvoteChoice');
    $this->choices = $choices;
    return $this;
  }

  public function getViewerChoices(PhabricatorUser $viewer) {
    return $this->assertAttachedKey($this->viewerChoices, $viewer->getPHID());
  }

  public function attachViewerChoices(PhabricatorUser $viewer, array $choices) {
    if ($this->viewerChoices === self::ATTACHABLE) {
      $this->viewerChoices = array();
    }
    assert_instances_of($choices, 'PhabricatorSlowvoteChoice');
    $this->viewerChoices[$viewer->getPHID()] = $choices;
    return $this;
  }

  public function getMonogram() {
    return 'V'.$this->getID();
  }

  public function save() {
    if (!$this->getMailKey()) {
      $this->setMailKey(Filesystem::readRandomCharacters(20));
    }
    return parent::save();
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new PhabricatorSlowvoteEditor();
  }

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new PhabricatorSlowvoteTransaction();
  }

  public function willRenderTimeline(
    PhabricatorApplicationTransactionView $timeline,
    AphrontRequest $request) {

    return $timeline;
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
        return $this->viewPolicy;
      case PhabricatorPolicyCapability::CAN_EDIT:
        return PhabricatorPolicies::POLICY_NOONE;
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return ($viewer->getPHID() == $this->getAuthorPHID());
  }

  public function describeAutomaticCapability($capability) {
    return pht('The author of a poll can always view and edit it.');
  }



/* -(  PhabricatorSubscribableInterface  )----------------------------------- */


  public function isAutomaticallySubscribed($phid) {
    return ($phid == $this->getAuthorPHID());
  }


/* -(  PhabricatorTokenReceiverInterface  )---------------------------------- */


  public function getUsersToNotifyOfTokenGiven() {
    return array($this->getAuthorPHID());
  }

/* -(  PhabricatorDestructibleInterface  )----------------------------------- */

  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    $this->openTransaction();
      $choices = id(new PhabricatorSlowvoteChoice())->loadAllWhere(
        'pollID = %d',
        $this->getID());
      foreach ($choices as $choice) {
        $choice->delete();
      }
      $options = id(new PhabricatorSlowvoteOption())->loadAllWhere(
        'pollID = %d',
        $this->getID());
      foreach ($options as $option) {
        $option->delete();
      }
      $this->delete();
    $this->saveTransaction();
  }

  /* -(  PhabricatorSpacesInterface  )--------------------------------------- */

  public function getSpacePHID() {
    return $this->spacePHID;
  }

}
