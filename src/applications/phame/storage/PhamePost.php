<?php

final class PhamePost extends PhameDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorMarkupInterface,
    PhabricatorFlaggableInterface,
    PhabricatorProjectInterface,
    PhabricatorApplicationTransactionInterface,
    PhabricatorSubscribableInterface,
    PhabricatorDestructibleInterface,
    PhabricatorTokenReceiverInterface {

  const MARKUP_FIELD_BODY    = 'markup:body';
  const MARKUP_FIELD_SUMMARY = 'markup:summary';

  protected $bloggerPHID;
  protected $title;
  protected $phameTitle;
  protected $body;
  protected $visibility;
  protected $configData;
  protected $datePublished;
  protected $blogPHID;
  protected $mailKey;

  private $blog;

  public static function initializePost(
    PhabricatorUser $blogger,
    PhameBlog $blog) {

    $post = id(new PhamePost())
      ->setBloggerPHID($blogger->getPHID())
      ->setBlogPHID($blog->getPHID())
      ->setBlog($blog)
      ->setDatePublished(PhabricatorTime::getNow())
      ->setVisibility(PhameConstants::VISIBILITY_PUBLISHED);
    return $post;
  }

  public function setBlog(PhameBlog $blog) {
    $this->blog = $blog;
    return $this;
  }

  public function getBlog() {
    return $this->blog;
  }

  public function getViewURI() {
    // go for the pretty uri if we can
    $domain = ($this->blog ? $this->blog->getDomain() : '');
    if ($domain) {
      $phame_title = PhabricatorSlug::normalize($this->getPhameTitle());
      return 'http://'.$domain.'/post/'.$phame_title;
    }
    $uri = '/phame/post/view/'.$this->getID().'/';
    return PhabricatorEnv::getProductionURI($uri);
  }

  public function getEditURI() {
    return '/phame/post/edit/'.$this->getID().'/';
  }

  public function isDraft() {
    return $this->getVisibility() == PhameConstants::VISIBILITY_DRAFT;
  }

  public function getHumanName() {
    if ($this->isDraft()) {
      $name = 'draft';
    } else {
      $name = 'post';
    }

    return $name;
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID   => true,
      self::CONFIG_SERIALIZATION => array(
        'configData' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'title' => 'text255',
        'phameTitle' => 'sort64',
        'visibility' => 'uint32',
        'mailKey' => 'bytes20',

        // T6203/NULLABILITY
        // These seem like they should always be non-null?
        'blogPHID' => 'phid?',
        'body' => 'text?',
        'configData' => 'text?',

        // T6203/NULLABILITY
        // This one probably should be nullable?
        'datePublished' => 'epoch',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_phid' => null,
        'phid' => array(
          'columns' => array('phid'),
          'unique' => true,
        ),
        'phameTitle' => array(
          'columns' => array('bloggerPHID', 'phameTitle'),
          'unique' => true,
        ),
        'bloggerPosts' => array(
          'columns' => array(
            'bloggerPHID',
            'visibility',
            'datePublished',
            'id',
          ),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function save() {
    if (!$this->getMailKey()) {
      $this->setMailKey(Filesystem::readRandomCharacters(20));
    }
    return parent::save();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorPhamePostPHIDType::TYPECONST);
  }

  public function toDictionary() {
    return array(
      'id'            => $this->getID(),
      'phid'          => $this->getPHID(),
      'blogPHID'      => $this->getBlogPHID(),
      'bloggerPHID'   => $this->getBloggerPHID(),
      'viewURI'       => $this->getViewURI(),
      'title'         => $this->getTitle(),
      'phameTitle'    => $this->getPhameTitle(),
      'body'          => $this->getBody(),
      'summary'       => PhabricatorMarkupEngine::summarize($this->getBody()),
      'datePublished' => $this->getDatePublished(),
      'published'     => !$this->isDraft(),
    );
  }


/* -(  PhabricatorPolicyInterface Implementation  )-------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    // Draft posts are visible only to the author. Published posts are visible
    // to whoever the blog is visible to.

    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        if (!$this->isDraft() && $this->getBlog()) {
          return $this->getBlog()->getViewPolicy();
        } else if ($this->getBlog()) {
          return $this->getBlog()->getEditPolicy();
        } else {
          return PhabricatorPolicies::POLICY_NOONE;
        }
        break;
      case PhabricatorPolicyCapability::CAN_EDIT:
        if ($this->getBlog()) {
          return $this->getBlog()->getEditPolicy();
        } else {
          return PhabricatorPolicies::POLICY_NOONE;
        }
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $user) {
    // A blog post's author can always view it.

    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
      case PhabricatorPolicyCapability::CAN_EDIT:
        return ($user->getPHID() == $this->getBloggerPHID());
    }
  }

  public function describeAutomaticCapability($capability) {
    return pht('The author of a blog post can always view and edit it.');
  }


/* -(  PhabricatorMarkupInterface Implementation  )-------------------------- */


  public function getMarkupFieldKey($field) {
    $hash = PhabricatorHash::digest($this->getMarkupText($field));
    return $this->getPHID().':'.$field.':'.$hash;
  }

  public function newMarkupEngine($field) {
    return PhabricatorMarkupEngine::newPhameMarkupEngine();
  }

  public function getMarkupText($field) {
    switch ($field) {
      case self::MARKUP_FIELD_BODY:
        return $this->getBody();
      case self::MARKUP_FIELD_SUMMARY:
        return PhabricatorMarkupEngine::summarize($this->getBody());
    }
  }

  public function didMarkupText(
    $field,
    $output,
    PhutilMarkupEngine $engine) {
    return $output;
  }

  public function shouldUseMarkupCache($field) {
    return (bool)$this->getPHID();
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new PhamePostEditor();
  }

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new PhamePostTransaction();
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

      $this->delete();

    $this->saveTransaction();
  }


/* -(  PhabricatorTokenReceiverInterface  )---------------------------------- */


  public function getUsersToNotifyOfTokenGiven() {
    return array(
      $this->getBloggerPHID(),
    );
  }


/* -(  PhabricatorSubscribableInterface Implementation  )-------------------- */


  public function isAutomaticallySubscribed($phid) {
    return ($this->bloggerPHID == $phid);
  }

  public function shouldShowSubscribersProperty() {
    return true;
  }

  public function shouldAllowSubscription($phid) {
    return true;
  }

}
