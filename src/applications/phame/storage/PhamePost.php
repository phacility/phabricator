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
    PhabricatorTokenReceiverInterface,
    PhabricatorConduitResultInterface,
    PhabricatorFulltextInterface {

  const MARKUP_FIELD_BODY    = 'markup:body';
  const MARKUP_FIELD_SUMMARY = 'markup:summary';

  protected $bloggerPHID;
  protected $title;
  protected $subtitle;
  protected $phameTitle;
  protected $body;
  protected $visibility;
  protected $configData;
  protected $datePublished;
  protected $blogPHID;
  protected $mailKey;
  protected $headerImagePHID;

  private $blog = self::ATTACHABLE;
  private $headerImageFile = self::ATTACHABLE;

  public static function initializePost(
    PhabricatorUser $blogger,
    PhameBlog $blog) {

    $post = id(new PhamePost())
      ->setBloggerPHID($blogger->getPHID())
      ->setBlogPHID($blog->getPHID())
      ->attachBlog($blog)
      ->setDatePublished(PhabricatorTime::getNow())
      ->setVisibility(PhameConstants::VISIBILITY_PUBLISHED);

    return $post;
  }

  public function attachBlog(PhameBlog $blog) {
    $this->blog = $blog;
    return $this;
  }

  public function getBlog() {
    return $this->assertAttached($this->blog);
  }

  public function getMonogram() {
    return 'J'.$this->getID();
  }

  public function getLiveURI() {
    $blog = $this->getBlog();
    $is_draft = $this->isDraft();
    $is_archived = $this->isArchived();
    if (strlen($blog->getDomain()) && !$is_draft && !$is_archived) {
      return $this->getExternalLiveURI();
    } else {
      return $this->getInternalLiveURI();
    }
  }

  public function getExternalLiveURI() {
    $id = $this->getID();
    $slug = $this->getSlug();
    $path = "/post/{$id}/{$slug}/";

    $domain = $this->getBlog()->getDomain();

    return (string)id(new PhutilURI('http://'.$domain))
      ->setPath($path);
  }

  public function getInternalLiveURI() {
    $id = $this->getID();
    $slug = $this->getSlug();
    $blog_id = $this->getBlog()->getID();
    return "/phame/live/{$blog_id}/post/{$id}/{$slug}/";
  }

  public function getViewURI() {
    $id = $this->getID();
    $slug = $this->getSlug();
    return "/phame/post/view/{$id}/{$slug}/";
  }

  public function getBestURI($is_live, $is_external) {
    if ($is_live) {
      if ($is_external) {
        return $this->getExternalLiveURI();
      } else {
        return $this->getInternalLiveURI();
      }
    } else {
      return $this->getViewURI();
    }
  }

  public function getEditURI() {
    return '/phame/post/edit/'.$this->getID().'/';
  }

  public function isDraft() {
    return ($this->getVisibility() == PhameConstants::VISIBILITY_DRAFT);
  }

  public function isArchived() {
    return ($this->getVisibility() == PhameConstants::VISIBILITY_ARCHIVED);
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID   => true,
      self::CONFIG_SERIALIZATION => array(
        'configData' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'title' => 'text255',
        'subtitle' => 'text64',
        'phameTitle' => 'sort64?',
        'visibility' => 'uint32',
        'mailKey' => 'bytes20',
        'headerImagePHID' => 'phid?',

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

  public function getSlug() {
    return PhabricatorSlug::normalizeProjectSlug($this->getTitle(), true);
  }

  public function getHeaderImageURI() {
    return $this->getHeaderImageFile()->getBestURI();
  }

  public function attachHeaderImageFile(PhabricatorFile $file) {
    $this->headerImageFile = $file;
    return $this;
  }

  public function getHeaderImageFile() {
    return $this->assertAttached($this->headerImageFile);
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
        if (!$this->isDraft() && !$this->isArchived() && $this->getBlog()) {
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


/* -(  PhabricatorConduitResultInterface  )---------------------------------- */


  public function getFieldSpecificationsForConduit() {
    return array(
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('title')
        ->setType('string')
        ->setDescription(pht('Title of the post.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('slug')
        ->setType('string')
        ->setDescription(pht('Slug for the post.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('blogPHID')
        ->setType('phid')
        ->setDescription(pht('PHID of the blog that the post belongs to.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('authorPHID')
        ->setType('phid')
        ->setDescription(pht('PHID of the author of the post.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('body')
        ->setType('string')
        ->setDescription(pht('Body of the post.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('datePublished')
        ->setType('epoch?')
        ->setDescription(pht('Publish date, if the post has been published.')),

    );
  }

  public function getFieldValuesForConduit() {
    if ($this->isDraft()) {
      $date_published = null;
    } else if ($this->isArchived()) {
      $date_published = null;
    } else {
      $date_published = (int)$this->getDatePublished();
    }

    return array(
      'title' => $this->getTitle(),
      'slug' => $this->getSlug(),
      'blogPHID' => $this->getBlogPHID(),
      'authorPHID' => $this->getBloggerPHID(),
      'body' => $this->getBody(),
      'datePublished' => $date_published,
    );
  }

  public function getConduitSearchAttachments() {
    return array();
  }


/* -(  PhabricatorFulltextInterface  )--------------------------------------- */

  public function newFulltextEngine() {
    return new PhamePostFulltextEngine();
  }

}
