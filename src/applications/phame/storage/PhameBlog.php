<?php

final class PhameBlog extends PhameDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorMarkupInterface,
    PhabricatorApplicationTransactionInterface {

  const MARKUP_FIELD_DESCRIPTION = 'markup:description';

  const SKIN_DEFAULT = 'oblivious';

  protected $name;
  protected $description;
  protected $domain;
  protected $configData;
  protected $creatorPHID;
  protected $viewPolicy;
  protected $editPolicy;
  protected $joinPolicy;

  private static $requestBlog;

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID   => true,
      self::CONFIG_SERIALIZATION => array(
        'configData' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'name' => 'text64',
        'description' => 'text',
        'domain' => 'text128?',

        // T6203/NULLABILITY
        // These policies should always be non-null.
        'joinPolicy' => 'policy?',
        'editPolicy' => 'policy?',
        'viewPolicy' => 'policy?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_phid' => null,
        'phid' => array(
          'columns' => array('phid'),
          'unique' => true,
        ),
        'domain' => array(
          'columns' => array('domain'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorPhameBlogPHIDType::TYPECONST);
  }

  public static function initializeNewBlog(PhabricatorUser $actor) {
    $blog = id(new PhameBlog())
      ->setCreatorPHID($actor->getPHID())
      ->setViewPolicy(PhabricatorPolicies::getMostOpenPolicy())
      ->setEditPolicy(PhabricatorPolicies::POLICY_USER)
      ->setJoinPolicy(PhabricatorPolicies::POLICY_USER);
    return $blog;
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
        pht(
          'This blog has an invalid skin, and the default skin failed to '.
          'load.'));
    }

    $skin = newv($spec->getSkinClass(), array());
    $skin->setRequest($request);
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
    $example_domain = 'blog.example.com';
    $label = pht('Invalid');

    // note this "uri" should be pretty busted given the desired input
    // so just use it to test if there's a protocol specified
    $uri = new PhutilURI($custom_domain);
    if ($uri->getProtocol()) {
      return array(
        $label,
        pht(
          'The custom domain should not include a protocol. Just provide '.
          'the bare domain name (for example, "%s").',
          $example_domain),
      );
    }

    if ($uri->getPort()) {
      return array(
        $label,
        pht(
          'The custom domain should not include a port number. Just provide '.
          'the bare domain name (for example, "%s").',
          $example_domain),
      );
    }

    if (strpos($custom_domain, '/') !== false) {
      return array(
        $label,
        pht(
          'The custom domain should not specify a path (hosting a Phame '.
          'blog at a path is currently not supported). Instead, just provide '.
          'the bare domain name (for example, "%s").',
          $example_domain),
        );
    }

    if (strpos($custom_domain, '.') === false) {
      return array(
        $label,
        pht(
          'The custom domain should contain at least one dot (.) because '.
          'some browsers fail to set cookies on domains without a dot. '.
          'Instead, use a normal looking domain name like "%s".',
          $example_domain),
        );
    }

    if (!PhabricatorEnv::getEnvConfig('policy.allow-public')) {
      $href = PhabricatorEnv::getProductionURI(
        '/config/edit/policy.allow-public/');
      return array(
        pht('Fix Configuration'),
        pht(
          'For custom domains to work, this Phabricator instance must be '.
          'configured to allow the public access policy. Configure this '.
          'setting %s, or ask an administrator to configure this setting. '.
          'The domain can be specified later once this setting has been '.
          'changed.',
          phutil_tag(
            'a',
            array('href' => $href),
            pht('here'))),
      );
    }

    return null;
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

  public static function getSkinOptionsForSelect() {
    $classes = id(new PhutilSymbolLoader())
      ->setAncestorClass('PhameBlogSkin')
      ->setType('class')
      ->setConcreteOnly(true)
      ->selectSymbolsWithoutLoading();

    return ipull($classes, 'name', 'name');
  }

  public static function setRequestBlog(PhameBlog $blog) {
    self::$requestBlog = $blog;
  }

  public static function getRequestBlog() {
    return self::$requestBlog;
  }

  public function getLiveURI(PhamePost $post = null) {
    if ($this->getDomain()) {
      $base = new PhutilURI('http://'.$this->getDomain().'/');
    } else {
      $base = '/phame/live/'.$this->getID().'/';
      $base = PhabricatorEnv::getURI($base);
    }

    if ($post) {
      $base .= '/post/'.$post->getPhameTitle();
    }

    return $base;
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


  public function describeAutomaticCapability($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return pht(
          'Users who can edit or post on a blog can always view it.');
      case PhabricatorPolicyCapability::CAN_JOIN:
        return pht(
          'Users who can edit a blog can always post on it.');
    }

    return null;
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


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new PhameBlogEditor();
  }

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new PhameBlogTransaction();
  }

  public function willRenderTimeline(
    PhabricatorApplicationTransactionView $timeline,
    AphrontRequest $request) {
    return $timeline;
  }

}
