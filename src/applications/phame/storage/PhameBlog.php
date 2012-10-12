<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * @group phame
 */
final class PhameBlog extends PhameDAO {

  const SKIN_DEFAULT = 'PhabricatorBlogSkin';

  protected $id;
  protected $phid;
  protected $name;
  protected $description;
  protected $domain;
  protected $configData;
  protected $creatorPHID;

  private $skin;

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

  public function getSkinRenderer() {
    $skin = $this->getSkin();

    return new $skin();
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

  public function getViewURI() {
    return $this->getActionURI('view');
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
}
