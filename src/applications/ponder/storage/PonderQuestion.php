<?php

final class PonderQuestion extends PonderDAO
  implements
    PhabricatorApplicationTransactionInterface,
    PhabricatorMarkupInterface,
    PhabricatorSubscribableInterface,
    PhabricatorFlaggableInterface,
    PhabricatorPolicyInterface,
    PhabricatorTokenReceiverInterface,
    PhabricatorProjectInterface,
    PhabricatorDestructibleInterface,
    PhabricatorSpacesInterface,
    PhabricatorFulltextInterface,
    PhabricatorFerretInterface {

  const MARKUP_FIELD_CONTENT = 'markup:content';

  protected $title;
  protected $phid;

  protected $authorPHID;
  protected $status;
  protected $content;
  protected $answerWiki;
  protected $contentSource;
  protected $viewPolicy;
  protected $spacePHID;

  protected $answerCount;
  protected $mailKey;

  private $answers;
  private $comments;

  private $projectPHIDs = self::ATTACHABLE;

  public static function initializeNewQuestion(PhabricatorUser $actor) {
    $app = id(new PhabricatorApplicationQuery())
      ->setViewer($actor)
      ->withClasses(array('PhabricatorPonderApplication'))
      ->executeOne();

    $view_policy = $app->getPolicy(
      PonderDefaultViewCapability::CAPABILITY);

    return id(new PonderQuestion())
      ->setAuthorPHID($actor->getPHID())
      ->setViewPolicy($view_policy)
      ->setStatus(PonderQuestionStatus::STATUS_OPEN)
      ->setAnswerCount(0)
      ->setAnswerWiki('')
      ->setSpacePHID($actor->getDefaultSpacePHID());
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'title' => 'text255',
        'status' => 'text32',
        'content' => 'text',
        'answerWiki' => 'text',
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

  public function setComments($comments) {
    $this->comments = $comments;
    return $this;
  }

  public function getComments() {
    return $this->comments;
  }

  public function getMonogram() {
    return 'Q'.$this->getID();
  }

  public function getViewURI() {
    return '/'.$this->getMonogram();
  }

  public function attachAnswers(array $answers) {
    assert_instances_of($answers, 'PonderAnswer');
    $this->answers = $answers;
    return $this;
  }

  public function getAnswers() {
    return $this->answers;
  }

  public function getProjectPHIDs() {
    return $this->assertAttached($this->projectPHIDs);
  }

  public function attachProjectPHIDs(array $phids) {
    $this->projectPHIDs = $phids;
    return $this;
  }

  public function getMarkupField() {
    return self::MARKUP_FIELD_CONTENT;
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new PonderQuestionEditor();
  }

  public function getApplicationTransactionTemplate() {
    return new PonderQuestionTransaction();
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

  public function save() {
    if (!$this->getMailKey()) {
      $this->setMailKey(Filesystem::readRandomCharacters(20));
    }
    return parent::save();
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
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return $this->getViewPolicy();
      case PhabricatorPolicyCapability::CAN_EDIT:
        $app = PhabricatorApplication::getByClass(
          'PhabricatorPonderApplication');
        return $app->getPolicy(PonderModerateCapability::CAPABILITY);
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    if ($capability == PhabricatorPolicyCapability::CAN_VIEW) {
      if (PhabricatorPolicyFilter::hasCapability(
        $viewer, $this, PhabricatorPolicyCapability::CAN_EDIT)) {
        return true;
      }
    }
    return ($viewer->getPHID() == $this->getAuthorPHID());
  }


  public function describeAutomaticCapability($capability) {
    $out = array();
    $out[] = pht('The user who asked a question can always view and edit it.');
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        $out[] = pht(
          'A moderator can always view the question.');
        break;
    }
    return $out;
  }


/* -(  PhabricatorSubscribableInterface  )----------------------------------- */


  public function isAutomaticallySubscribed($phid) {
    return ($phid == $this->getAuthorPHID());
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


/* -(  PhabricatorSpacesInterface  )----------------------------------------- */


  public function getSpacePHID() {
    return $this->spacePHID;
  }


/* -(  PhabricatorFulltextInterface  )--------------------------------------- */


  public function newFulltextEngine() {
    return new PonderQuestionFulltextEngine();
  }


/* -(  PhabricatorFerretInterface  )----------------------------------------- */


  public function newFerretEngine() {
    return new PonderQuestionFerretEngine();
  }


}
