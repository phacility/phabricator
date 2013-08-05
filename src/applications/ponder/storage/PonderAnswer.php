<?php

final class PonderAnswer extends PonderDAO
  implements
    PhabricatorMarkupInterface,
    PonderVotableInterface,
    PhabricatorPolicyInterface,
    PhabricatorSubscribableInterface,
    PhabricatorTokenReceiverInterface {

  const MARKUP_FIELD_CONTENT = 'markup:content';

  protected $authorPHID;
  protected $questionID;

  protected $content;
  protected $contentSource;

  protected $voteCount;
  private $vote;
  private $question = self::ATTACHABLE;
  private $comments;

  private $userVotes = array();

  // TODO: Get rid of this method.
  public function setQuestion($question) {
    return $this->attachQuestion($question);
  }

  public function attachQuestion(PonderQuestion $question = null) {
    $this->question = $question;
    return $this;
  }

  public function getQuestion() {
    return $this->assertAttached($this->question);
  }

  public function setUserVote($vote) {
    $this->vote = $vote['data'];
    if (!$this->vote) {
      $this->vote = PonderVote::VOTE_NONE;
    }
    return $this;
  }

  public function attachUserVote($user_phid, $vote) {
    $this->vote = $vote;
    return $this;
  }

  public function getUserVote() {
    return $this->vote;
  }

  public function setComments($comments) {
    $this->comments = $comments;
    return $this;
  }

  public function getComments() {
    return $this->comments;
  }

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PonderPHIDTypeAnswer::TYPECONST);
  }

  public function setContentSource(PhabricatorContentSource $content_source) {
    $this->contentSource = $content_source->serialize();
    return $this;
  }

  public function getContentSource() {
    return PhabricatorContentSource::newFromSerialized($this->contentSource);
  }

  public function getMarkupField() {
    return self::MARKUP_FIELD_CONTENT;
  }

  // Markup interface

  public function getMarkupFieldKey($field) {
    $hash = PhabricatorHash::digest($this->getMarkupText($field));
    $id = $this->getID();
    return "ponder:A{$id}:{$field}:{$hash}";
  }

  public function getMarkupText($field) {
    return $this->getContent();
  }

  public function newMarkupEngine($field) {
    return PhabricatorMarkupEngine::getEngine();
  }

  public function didMarkupText(
    $field,
    $output,
    PhutilMarkupEngine $engine) {
    return $output;
  }

  public function shouldUseMarkupCache($field) {
    return (bool)$this->getID();
  }

  // votable interface
  public function getUserVoteEdgeType() {
    return PhabricatorEdgeConfig::TYPE_VOTING_USER_HAS_ANSWER;
  }

  public function getVotablePHID() {
    return $this->getPHID();
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
        return $this->getQuestion()->getPolicy($capability);
      case PhabricatorPolicyCapability::CAN_EDIT:
        return PhabricatorPolicies::POLICY_NOONE;
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return $this->getQuestion()->hasAutomaticCapability(
          $capability,
          $viewer);
      case PhabricatorPolicyCapability::CAN_EDIT:
        return ($this->getAuthorPHID() == $viewer->getPHID());
    }
  }


/* -(  PhabricatorTokenReceiverInterface  )---------------------------------- */


  public function getUsersToNotifyOfTokenGiven() {
    return array(
      $this->getAuthorPHID(),
    );
  }


/* -(  PhabricatorSubscribableInterface  )----------------------------------- */


  public function isAutomaticallySubscribed($phid) {
    return ($phid == $this->getAuthorPHID());
  }


}
