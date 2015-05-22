<?php

final class PonderQuestion extends PonderDAO
  implements
    PhabricatorApplicationTransactionInterface,
    PhabricatorMarkupInterface,
    PonderVotableInterface,
    PhabricatorSubscribableInterface,
    PhabricatorFlaggableInterface,
    PhabricatorPolicyInterface,
    PhabricatorTokenReceiverInterface,
    PhabricatorProjectInterface,
    PhabricatorDestructibleInterface {

  const MARKUP_FIELD_CONTENT = 'markup:content';

  protected $title;
  protected $phid;

  protected $authorPHID;
  protected $status;
  protected $content;
  protected $contentSource;

  protected $voteCount;
  protected $answerCount;
  protected $heat;
  protected $mailKey;

  private $answers;
  private $vote;
  private $comments;

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'title' => 'text255',
        'voteCount' => 'sint32',
        'status' => 'uint32',
        'content' => 'text',
        'heat' => 'double',
        'answerCount' => 'uint32',
        'mailKey' => 'bytes20',

        // T6203/NULLABILITY
        // This should always exist.
        'contentSource' => 'text?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_phid' => null,
        'phid' => array(
          'columns' => array('phid'),
          'unique' => true,
        ),
        'authorPHID' => array(
          'columns' => array('authorPHID'),
        ),
        'heat' => array(
          'columns' => array('heat'),
        ),
        'status' => array(
          'columns' => array('status'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(PonderQuestionPHIDType::TYPECONST);
  }

  public function setContentSource(PhabricatorContentSource $content_source) {
    $this->contentSource = $content_source->serialize();
    return $this;
  }

  public function getContentSource() {
    return PhabricatorContentSource::newFromSerialized($this->contentSource);
  }

  public function attachVotes($user_phid) {
    $qa_phids = mpull($this->answers, 'getPHID') + array($this->getPHID());

    $edges = id(new PhabricatorEdgeQuery())
      ->withSourcePHIDs(array($user_phid))
      ->withDestinationPHIDs($qa_phids)
      ->withEdgeTypes(
        array(
          PonderVotingUserHasQuestionEdgeType::EDGECONST,
          PonderVotingUserHasAnswerEdgeType::EDGECONST,
        ))
      ->needEdgeData(true)
      ->execute();

    $question_edge =
      $edges[$user_phid][PonderVotingUserHasQuestionEdgeType::EDGECONST];
    $answer_edges =
      $edges[$user_phid][PonderVotingUserHasAnswerEdgeType::EDGECONST];
    $edges = null;

    $this->setUserVote(idx($question_edge, $this->getPHID()));
    foreach ($this->answers as $answer) {
      $answer->setUserVote(idx($answer_edges, $answer->getPHID()));
    }
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

  public function attachAnswers(array $answers) {
    assert_instances_of($answers, 'PonderAnswer');
    $this->answers = $answers;
    return $this;
  }

  public function getAnswers() {
    return $this->answers;
  }

  public function getMarkupField() {
    return self::MARKUP_FIELD_CONTENT;
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new PonderQuestionEditor();
  }

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new PonderQuestionTransaction();
  }

  public function willRenderTimeline(
    PhabricatorApplicationTransactionView $timeline,
    AphrontRequest $request) {

    return $timeline;
  }


  // Markup interface

  public function getMarkupFieldKey($field) {
    $hash = PhabricatorHash::digest($this->getMarkupText($field));
    $id = $this->getID();
    return "ponder:Q{$id}:{$field}:{$hash}";
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
    return PonderVotingUserHasQuestionEdgeType::EDGECONST;
  }

  public function getVotablePHID() {
    return $this->getPHID();
  }

  public function save() {
    if (!$this->getMailKey()) {
      $this->setMailKey(Filesystem::readRandomCharacters(20));
    }
    return parent::save();
  }

  public function getOriginalTitle() {
    // TODO: Make this actually save/return the original title.
    return $this->getTitle();
  }

  public function getFullTitle() {
    $id = $this->getID();
    $title = $this->getTitle();
    return "Q{$id}: {$title}";
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */

  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    $policy = PhabricatorPolicies::POLICY_NOONE;

    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        $policy = PhabricatorPolicies::POLICY_USER;
        break;
    }

    return $policy;
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return ($viewer->getPHID() == $this->getAuthorPHID());
  }


  public function describeAutomaticCapability($capability) {
    return pht('The user who asked a question can always view and edit it.');
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
    return array(
      $this->getAuthorPHID(),
    );
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */

  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    $this->openTransaction();
      $answers = id(new PonderAnswer())->loadAllWhere(
        'questionID = %d',
        $this->getID());
      foreach ($answers as $answer) {
        $engine->destroyObject($answer);
      }

      $this->delete();
    $this->saveTransaction();
  }

}
