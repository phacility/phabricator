<?php

/**
 * @group phame
 */
final class PhameBlog extends PhameDAO
  implements PhabricatorPolicyInterface, PhabricatorMarkupInterface {

  const MARKUP_FIELD_DESCRIPTION = 'markup:description';

  const SKIN_DEFAULT = 'oblivious';

  protected $id;
  protected $phid;
  protected $name;
  protected $description;
  protected $domain;
  protected $configData;
  protected $creatorPHID;
  protected $viewPolicy;
  protected $editPolicy;
  protected $joinPolicy;

  private $bloggerPHIDs;
  private $bloggers;

  static private $requestBlog;

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID   => true,
      self::CONFIG_SERIALIZATION => array(
        'configData' => self::SERIALIZATION_JSON,
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorPHIDConstants::PHID_TYPE_BLOG);
  }

  public function getSkinRenderer(AphrontRequest $request) {
    $spec = PhameSkinSpecification::loadOneSkinSpecification(
      $this->getSkin());

    if (!$spec) {
      $spec = PhameSkinSpecification::loadOneSkinSpecification(
        self::SKIN_DEFAULT);
    }

    if (!$spec) {
      throw new Exception(
        "This blog has an invalid skin, and the default skin failed to ".
        "load.");
    }

    $skin = newv($spec->getSkinClass(), array($request));
    $skin->setSpecification($spec);

    return $skin;
  }

  /**
   * Makes sure a given custom blog uri is properly configured in DNS
   * to point at this Phabricator instance. If there is an error in
   * the configuration, return a string describing the error and how
   * to fix it. If there is no error, return an empty string.
   *
   * @return string
   */
  public function validateCustomDomain($custom_domain) {
    $example_domain = '(e.g. blog.example.com)';
    $valid          = '';

    // note this "uri" should be pretty busted given the desired input
    // so just use it to test if there's a protocol specified
    $uri = new PhutilURI($custom_domain);
    if ($uri->getProtocol()) {
      return 'Do not specify a protocol, just the domain. '.$example_domain;
    }

    if (strpos($custom_domain, '/') !== false) {
      return 'Do not specify a path, just the domain. '.$example_domain;
    }

    if (strpos($custom_domain, '.') === false) {
      return 'Custom domain must contain at least one dot (.) because '.
        'some browsers fail to set cookies on domains such as '.
        'http://example. '.$example_domain;
    }

    return $valid;
  }

  public function loadBloggerPHIDs() {
    if (!$this->getPHID()) {
      return $this;
    }

    if ($this->bloggerPHIDs) {
      return $this;
    }

    $this->bloggerPHIDs = PhabricatorEdgeQuery::loadDestinationPHIDs(
      $this->getPHID(),
      PhabricatorEdgeConfig::TYPE_BLOG_HAS_BLOGGER
    );

    return $this;
  }

  public function getBloggerPHIDs() {
    if ($this->bloggerPHIDs === null) {
      throw new Exception(
        'You must loadBloggerPHIDs before you can getBloggerPHIDs!'
      );
    }

    return $this->bloggerPHIDs;
  }

  public function loadBloggers() {
    if ($this->bloggers) {
      return $this->bloggers;
    }

    $blogger_phids = $this->loadBloggerPHIDs()->getBloggerPHIDs();

    if (empty($blogger_phids)) {
      return array();
    }

    $bloggers = id(new PhabricatorObjectHandleData($blogger_phids))
      ->loadHandles();

    $this->attachBloggers($bloggers);

    return $this;
  }

  public function attachBloggers(array $bloggers) {
    assert_instances_of($bloggers, 'PhabricatorObjectHandle');

    $this->bloggers = $bloggers;

    return $this;
  }

  public function getBloggers() {
    if ($this->bloggers === null) {
      throw new Exception(
        'You must loadBloggers or attachBloggers before you can getBloggers!'
      );
    }

    return $this->bloggers;
  }

  public function getSkin() {
    $config = coalesce($this->getConfigData(), array());
    return idx($config, 'skin', self::SKIN_DEFAULT);
  }

  public function setSkin($skin) {
    $config = coalesce($this->getConfigData(), array());
    $config['skin'] = $skin;
    return $this->setConfigData($config);
  }

  static public function getSkinOptionsForSelect() {
    $classes = id(new PhutilSymbolLoader())
      ->setAncestorClass('PhameBlogSkin')
      ->setType('class')
      ->setConcreteOnly(true)
      ->selectSymbolsWithoutLoading();

    return ipull($classes, 'name', 'name');
  }

  public function getPostListURI() {
    return $this->getActionURI('posts');
  }

  public function getEditURI() {
    return $this->getActionURI('edit');
  }

  public function getEditFilter() {
    return 'blog/edit/'.$this->getPHID();
  }

  public function getDeleteURI() {
    return $this->getActionURI('delete');
  }

  private function getActionURI($action) {
    return '/phame/blog/'.$action.'/'.$this->getPHID().'/';
  }

  public static function setRequestBlog(PhameBlog $blog) {
    self::$requestBlog = $blog;
  }

  public static function getRequestBlog() {
    return self::$requestBlog;
  }


/* -(  PhabricatorPolicyInterface Implementation  )-------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
      PhabricatorPolicyCapability::CAN_JOIN,
    );
  }


  public function getPolicy($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return $this->getViewPolicy();
      case PhabricatorPolicyCapability::CAN_EDIT:
        return $this->getEditPolicy();
      case PhabricatorPolicyCapability::CAN_JOIN:
        return $this->getJoinPolicy();
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $user) {
    $can_edit = PhabricatorPolicyCapability::CAN_EDIT;
    $can_join = PhabricatorPolicyCapability::CAN_JOIN;

    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        // Users who can edit or post to a blog can always view it.
        if (PhabricatorPolicyFilter::hasCapability($user, $this, $can_edit)) {
          return true;
        }
        if (PhabricatorPolicyFilter::hasCapability($user, $this, $can_join)) {
          return true;
        }
        break;
      case PhabricatorPolicyCapability::CAN_JOIN:
        // Users who can edit a blog can always post to it.
        if (PhabricatorPolicyFilter::hasCapability($user, $this, $can_edit)) {
          return true;
        }
        break;
    }

    return false;
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
    return $this->getDescription();
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

}
