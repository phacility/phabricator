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

  protected $id;
  protected $phid;
  protected $name;
  protected $description;
  protected $configData;
  protected $creatorPHID;

  private $bloggerPHIDs;
  private $bloggers;

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
}
