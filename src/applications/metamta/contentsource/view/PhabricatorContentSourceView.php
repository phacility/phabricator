<?php

/*
 * Copyright 2011 Facebook, Inc.
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

final class PhabricatorContentSourceView extends AphrontView {

  private $contentSource;
  private $user;

  public function setContentSource(PhabricatorContentSource $content_source) {
    $this->contentSource = $content_source;
    return $this;
  }

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }


  public function render() {
    require_celerity_resource('phabricator-content-source-view-css');

    $type = null;
    $map = array(
      PhabricatorContentSource::SOURCE_WEB      => 'web',
      PhabricatorContentSource::SOURCE_CONDUIT  => 'conduit',
      PhabricatorContentSource::SOURCE_EMAIL    => 'email',
      PhabricatorContentSource::SOURCE_MOBILE   => 'mobile',
      PhabricatorContentSource::SOURCE_TABLET   => 'tablet',
    );

    $source = $this->contentSource->getSource();
    $type = idx($map, $source, null);

    if (!$type) {
      return;
    }

    $type_class = 'phabricator-content-source-'.$type;

    return phutil_render_tag(
      'span',
      array(
        'class' => "phabricator-content-source-view {$type_class}",
      ),
      'Via');
  }

}
