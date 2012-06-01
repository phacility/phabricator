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
final class PhamePost extends PhameDAO {

  const VISIBILITY_DRAFT     = 0;
  const VISIBILITY_PUBLISHED = 1;

  protected $id;
  protected $phid;
  protected $bloggerPHID;
  protected $title;
  protected $phameTitle;
  protected $body;
  protected $visibility;
  protected $configData;
  protected $datePublished;

  public function getViewURI($blogger_name = '') {
    // go for the pretty uri if we can
    if ($blogger_name) {
      $phame_title = PhabricatorSlug::normalize($this->getPhameTitle());
      $uri = phutil_escape_uri('/phame/posts/'.$blogger_name.'/'.$phame_title);
    } else {
      $uri = $this->getActionURI('view');
    }
    return $uri;
  }
  public function getEditURI() {
    return $this->getActionURI('edit');
  }
  public function getDeleteURI() {
    return $this->getActionURI('delete');
  }
  private function getActionURI($action) {
    return '/phame/post/'.$action.'/'.$this->getPHID().'/';
  }

  public function isDraft() {
    return $this->getVisibility() == self::VISIBILITY_DRAFT;
  }

  public function getCommentsWidget() {
    $config_data = $this->getConfigData();
    if (empty($config_data)) {
      return 'none';
    }
    return idx($config_data, 'comments_widget', 'none');
  }
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
      PhabricatorPHIDConstants::PHID_TYPE_POST);
  }

  public static function getVisibilityOptionsForSelect() {
    return array(
      self::VISIBILITY_DRAFT     => 'Draft: visible only to me.',
      self::VISIBILITY_PUBLISHED => 'Published: visible to the whole world.',
    );
  }

  public function getCommentsWidgetOptionsForSelect() {
    $current = $this->getCommentsWidget();
    $options = array();

    if ($current == 'facebook' ||
        PhabricatorEnv::getEnvConfig('facebook.application-id')) {
      $options['facebook'] = 'Facebook';
    }
    if ($current == 'disqus' ||
        PhabricatorEnv::getEnvConfig('disqus.shortname')) {
      $options['disqus'] = 'Disqus';
    }
    $options['none'] = 'None';

    return $options;
  }

}
