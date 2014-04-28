<?php

/**
 * @group slowvote
 */
final class PhabricatorSlowvotePoll extends PhabricatorSlowvoteDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorSubscribableInterface,
    PhabricatorFlaggableInterface,
    PhabricatorTokenReceiverInterface {

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
  protected $viewPolicy;
  protected $isClosed = 0;

  private $options = self::ATTACHABLE;
  private $choices = self::ATTACHABLE;
  private $viewerChoices = self::ATTACHABLE;

  public static function initializeNewPoll(PhabricatorUser $actor) {
    $app = id(new PhabricatorApplicationQuery())
      ->setViewer($actor)
      ->withClasses(array('PhabricatorApplicationSlowvote'))
      ->executeOne();

    $view_policy = $app->getPolicy(
      PhabricatorSlowvoteCapabilityDefaultView::CAPABILITY);

    return id(new PhabricatorSlowvotePoll())
      ->setAuthorPHID($actor->getPHID())
      ->setViewPolicy($view_policy);
  }

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorSlowvotePHIDTypePoll::TYPECONST);
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
    return pht(
      'The author of a poll can always view and edit it.');
  }



/* -(  PhabricatorSubscribableInterface  )----------------------------------- */


  public function isAutomaticallySubscribed($phid) {
    return ($phid == $this->getAuthorPHID());
  }

  public function shouldShowSubscribersProperty() {
    return true;
  }

  public function shouldAllowSubscription($phid) {
    return true;
  }


/* -(  PhabricatorTokenReceiverInterface  )---------------------------------- */


  public function getUsersToNotifyOfTokenGiven() {
    return array($this->getAuthorPHID());
  }


}
