<?php

final class PonderAnswer extends PonderDAO
  implements
    PhabricatorApplicationTransactionInterface,
    PhabricatorMarkupInterface,
    PhabricatorPolicyInterface,
    PhabricatorFlaggableInterface,
    PhabricatorSubscribableInterface,
    PhabricatorDestructibleInterface {

  const MARKUP_FIELD_CONTENT = 'markup:content';

  protected $authorPHID;
  protected $questionID;

  protected $content;
  protected $mailKey;
  protected $status;
  protected $voteCount;

  private $question = self::ATTACHABLE;
  private $comments;

  public static function initializeNewAnswer(
    PhabricatorUser $actor,
    PonderQuestion $question) {
    $app = id(new PhabricatorApplicationQuery())
      ->setViewer($actor)
      ->withClasses(array('PhabricatorPonderApplication'))
      ->executeOne();

    return id(new PonderAnswer())
      ->setQuestionID($question->getID())
      ->setContent('')
      ->attachQuestion($question)
      ->setAuthorPHID($actor->getPHID())
      ->setVoteCount(0)
      ->setStatus(PonderAnswerStatus::ANSWER_STATUS_VISIBLE);

  }

  public function attachQuestion(PonderQuestion $question = null) {
    $this->question = $question;
    return $this;
  }

  public function getQuestion() {
    return $this->assertAttached($this->question);
  }

  public function getURI() {
    return '/Q'.$this->getQuestionID().'#A'.$this->getID();
  }

  public function setComments($comments) {
    $this->comments = $comments;
    return $this;
  }

  public function getComments() {
    return $this->comments;
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'voteCount' => 'sint32',
        'content' => 'text',
        'status' => 'text32',
        'mailKey' => 'bytes20',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_phid' => null,
        'phid' => array(
          'columns' => array('phid'),
          'unique' => true,
        ),
        'key_oneanswerperquestion' => array(
          'columns' => array('questionID', 'authorPHID'),
          'unique' => true,
        ),
        'questionID' => array(
          'columns' => array('questionID'),
        ),
        'authorPHID' => array(
          'columns' => array('authorPHID'),
        ),
        'status' => array(
          'columns' => array('status'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(PonderAnswerPHIDType::TYPECONST);
  }

  public function getMarkupField() {
    return self::MARKUP_FIELD_CONTENT;
  }

  public function save() {
    if (!$this->getMailKey()) {
      $this->setMailKey(Filesystem::readRandomCharacters(20));
    }
    return parent::save();
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new PonderAnswerEditor();
  }

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new PonderAnswerTransaction();
  }

  public function willRenderTimeline(
    PhabricatorApplicationTransactionView $timeline,
    AphrontRequest $request) {

    return $timeline;
  }


  // Markup interface

  public function getMarkupFieldKey($field) {
    $content = $this->getMarkupText($field);
    return PhabricatorMarkupEngine::digestRemarkupContent($this, $content);
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
        $app = PhabricatorApplication::getByClass(
          'PhabricatorPonderApplication');
        return $app->getPolicy(PonderModerateCapability::CAPABILITY);
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        if ($this->getAuthorPHID() == $viewer->getPHID()) {
          return true;
        }
        return $this->getQuestion()->hasAutomaticCapability(
          $capability,
          $viewer);
      case PhabricatorPolicyCapability::CAN_EDIT:
        return ($this->getAuthorPHID() == $viewer->getPHID());
    }
  }


  public function describeAutomaticCapability($capability) {
    $out = array();
    $out[] = pht('The author of an answer can always view and edit it.');
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        $out[] = pht(
          'The user who asks a question can always view the answers.');
        $out[] = pht(
          'A moderator can always view the answers.');
        break;
    }
    return $out;
  }


/* -(  PhabricatorSubscribableInterface  )----------------------------------- */


  public function isAutomaticallySubscribed($phid) {
    return ($phid == $this->getAuthorPHID());
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    $this->openTransaction();
      $this->delete();
    $this->saveTransaction();
  }

}
