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

    $map = array(
      PhabricatorContentSource::SOURCE_WEB      => 'Web',
      PhabricatorContentSource::SOURCE_CONDUIT  => 'Conduit',
      PhabricatorContentSource::SOURCE_EMAIL    => 'Email',
      PhabricatorContentSource::SOURCE_MOBILE   => 'Mobile',
      PhabricatorContentSource::SOURCE_TABLET   => 'Tablet',
      PhabricatorContentSource::SOURCE_FAX      => 'Fax',
    );

    $source = $this->contentSource->getSource();
    $type = idx($map, $source, null);

    if (!$type) {
      return;
    }

    return phutil_render_tag(
      'span',
      array(
        'class' => "phabricator-content-source-view",
      ),
      "Via {$type}");
  }

}
