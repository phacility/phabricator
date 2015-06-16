<?php

final class PhamePost extends PhameDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorMarkupInterface,
    PhabricatorApplicationTransactionInterface,
    PhabricatorTokenReceiverInterface {

  const MARKUP_FIELD_BODY    = 'markup:body';
  const MARKUP_FIELD_SUMMARY = 'markup:summary';

  const VISIBILITY_DRAFT     = 0;
  const VISIBILITY_PUBLISHED = 1;

  protected $bloggerPHID;
  protected $title;
  protected $phameTitle;
  protected $body;
  protected $visibility;
  protected $configData;
  protected $datePublished;
  protected $blogPHID;

  private $blog;

  public static function initializePost(
    PhabricatorUser $blogger,
    PhameBlog $blog) {

    $post = id(new PhamePost())
      ->setBloggerPHID($blogger->getPHID())
      ->setBlogPHID($blog->getPHID())
      ->setBlog($blog)
      ->setDatePublished(0)
      ->setVisibility(self::VISIBILITY_DRAFT);
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
    return $this->getVisibility() == self::VISIBILITY_DRAFT;
  }

  public function getHumanName() {
    if ($this->isDraft()) {
      $name = 'draft';
    } else {
      $name = 'post';
    }

    return $name;
  }

  public function setCommentsWidget($widget) {
    $config_data = $this->getConfigData();
    $config_data['comments_widget'] = $widget;
    return $this;
  }

  public function getCommentsWidget() {
    $config_data = $this->getConfigData();
    if (empty($config_data)) {
      return 'none';
    }
    return idx($config_data, 'comments_widget', 'none');
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

  public static function getVisibilityOptionsForSelect() {
    return array(
      self::VISIBILITY_DRAFT     => pht('Draft: visible only to me.'),
      self::VISIBILITY_PUBLISHED => pht(
        'Published: visible to the whole world.'),
    );
  }

  public function getCommentsWidgetOptionsForSelect() {
    $current = $this->getCommentsWidget();
    $options = array();

    if ($current == 'facebook' ||
        PhabricatorFacebookAuthProvider::getFacebookApplicationID()) {
      $options['facebook'] = pht('Facebook');
    }
    if ($current == 'disqus' ||
        PhabricatorEnv::getEnvConfig('disqus.shortname')) {
      $options['disqus'] = pht('Disqus');
    }
    $options['none'] = pht('None');

    return $options;
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
        } else {
          return PhabricatorPolicies::POLICY_NOONE;
        }
        break;
      case PhabricatorPolicyCapability::CAN_EDIT:
        return PhabricatorPolicies::POLICY_NOONE;
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $user) {
    // A blog post's author can always view it, and is the only user allowed
    // to edit it.

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


/* -(  PhabricatorTokenReceiverInterface  )---------------------------------- */


  public function getUsersToNotifyOfTokenGiven() {
    return array(
      $this->getBloggerPHID(),
    );
  }

}
