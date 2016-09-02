<?php

final class PhabricatorPaste extends PhabricatorPasteDAO
  implements
    PhabricatorSubscribableInterface,
    PhabricatorTokenReceiverInterface,
    PhabricatorFlaggableInterface,
    PhabricatorMentionableInterface,
    PhabricatorPolicyInterface,
    PhabricatorProjectInterface,
    PhabricatorDestructibleInterface,
    PhabricatorApplicationTransactionInterface,
    PhabricatorSpacesInterface,
    PhabricatorConduitResultInterface {

  protected $title;
  protected $authorPHID;
  protected $filePHID;
  protected $language;
  protected $parentPHID;
  protected $viewPolicy;
  protected $editPolicy;
  protected $mailKey;
  protected $status;
  protected $spacePHID;

  const STATUS_ACTIVE = 'active';
  const STATUS_ARCHIVED = 'archived';

  private $content = self::ATTACHABLE;
  private $rawContent = self::ATTACHABLE;
  private $snippet = self::ATTACHABLE;

  public static function initializeNewPaste(PhabricatorUser $actor) {
    $app = id(new PhabricatorApplicationQuery())
      ->setViewer($actor)
      ->withClasses(array('PhabricatorPasteApplication'))
      ->executeOne();

    $view_policy = $app->getPolicy(PasteDefaultViewCapability::CAPABILITY);
    $edit_policy = $app->getPolicy(PasteDefaultEditCapability::CAPABILITY);

    return id(new PhabricatorPaste())
      ->setTitle('')
      ->setStatus(self::STATUS_ACTIVE)
      ->setAuthorPHID($actor->getPHID())
      ->setViewPolicy($view_policy)
      ->setEditPolicy($edit_policy)
      ->setSpacePHID($actor->getDefaultSpacePHID())
      ->attachRawContent(null);
  }

  public static function getStatusNameMap() {
    return array(
      self::STATUS_ACTIVE => pht('Active'),
      self::STATUS_ARCHIVED => pht('Archived'),
    );
  }

  public function getURI() {
    return '/'.$this->getMonogram();
  }

  public function getMonogram() {
    return 'P'.$this->getID();
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'status' => 'text32',
        'title' => 'text255',
        'language' => 'text64?',
        'mailKey' => 'bytes20',
        'parentPHID' => 'phid?',

        // T6203/NULLABILITY
        // Pastes should always have a view policy.
        'viewPolicy' => 'policy?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'parentPHID' => array(
          'columns' => array('parentPHID'),
        ),
        'authorPHID' => array(
          'columns' => array('authorPHID'),
        ),
        'key_dateCreated' => array(
          'columns' => array('dateCreated'),
        ),
        'key_language' => array(
          'columns' => array('language'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorPastePastePHIDType::TYPECONST);
  }

  public function isArchived() {
    return ($this->getStatus() == self::STATUS_ARCHIVED);
  }

  public function save() {
    if (!$this->getMailKey()) {
      $this->setMailKey(Filesystem::readRandomCharacters(20));
    }
    return parent::save();
  }

  public function getFullName() {
    $title = $this->getTitle();
    if (!$title) {
      $title = pht('(An Untitled Masterwork)');
    }
    return 'P'.$this->getID().' '.$title;
  }

  public function getContent() {
    return $this->assertAttached($this->content);
  }

  public function attachContent($content) {
    $this->content = $content;
    return $this;
  }

  public function getRawContent() {
    return $this->assertAttached($this->rawContent);
  }

  public function attachRawContent($raw_content) {
    $this->rawContent = $raw_content;
    return $this;
  }

  public function getSnippet() {
    return $this->assertAttached($this->snippet);
  }

  public function attachSnippet(PhabricatorPasteSnippet $snippet) {
    $this->snippet = $snippet;
    return $this;
  }

/* -(  PhabricatorSubscribableInterface  )----------------------------------- */


  public function isAutomaticallySubscribed($phid) {
    return ($this->authorPHID == $phid);
  }


/* -(  PhabricatorTokenReceiverInterface  )---------------------------------- */

  public function getUsersToNotifyOfTokenGiven() {
    return array(
      $this->getAuthorPHID(),
    );
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    if ($capability == PhabricatorPolicyCapability::CAN_VIEW) {
      return $this->viewPolicy;
    } else if ($capability == PhabricatorPolicyCapability::CAN_EDIT) {
      return $this->editPolicy;
    }
    return PhabricatorPolicies::POLICY_NOONE;
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $user) {
    return ($user->getPHID() == $this->getAuthorPHID());
  }

  public function describeAutomaticCapability($capability) {
    return pht('The author of a paste can always view and edit it.');
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    if ($this->filePHID) {
      $file = id(new PhabricatorFileQuery())
        ->setViewer($engine->getViewer())
        ->withPHIDs(array($this->filePHID))
        ->executeOne();
      if ($file) {
        $engine->destroyObject($file);
      }
    }

    $this->delete();
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new PhabricatorPasteEditor();
  }

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new PhabricatorPasteTransaction();
  }

  public function willRenderTimeline(
    PhabricatorApplicationTransactionView $timeline,
    AphrontRequest $request) {

    return $timeline;
  }


/* -(  PhabricatorSpacesInterface  )----------------------------------------- */


  public function getSpacePHID() {
    return $this->spacePHID;
  }


/* -(  PhabricatorConduitResultInterface  )---------------------------------- */


  public function getFieldSpecificationsForConduit() {
    return array(
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('title')
        ->setType('string')
        ->setDescription(pht('The title of the paste.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('authorPHID')
        ->setType('phid')
        ->setDescription(pht('User PHID of the author.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('language')
        ->setType('string?')
        ->setDescription(pht('Language to use for syntax highlighting.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('status')
        ->setType('string')
        ->setDescription(pht('Active or archived status of the paste.')),
    );
  }

  public function getFieldValuesForConduit() {
    return array(
      'title' => $this->getTitle(),
      'authorPHID' => $this->getAuthorPHID(),
      'language' => nonempty($this->getLanguage(), null),
      'status' => $this->getStatus(),
    );
  }

  public function getConduitSearchAttachments() {
    return array(
      id(new PhabricatorPasteContentSearchEngineAttachment())
        ->setAttachmentKey('content'),
    );
  }

}
