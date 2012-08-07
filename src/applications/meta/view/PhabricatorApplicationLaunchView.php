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

final class PhabricatorApplicationLaunchView extends AphrontView {

  private $user;
  private $application;
  private $status;

  public function setApplication(PhabricatorApplication $application) {
    $this->application = $application;
    return $this;
  }

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function setApplicationStatus(array $status) {
    $this->status = $status;
    return $this;
  }

  public function render() {
    $application = $this->application;

    require_celerity_resource('phabricator-application-launch-view-css');

    $content = array();
    $content[] = phutil_render_tag(
      'span',
      array(
        'class' => 'phabricator-application-launch-name',
      ),
      phutil_escape_html($application->getName()));
    $content[] = phutil_render_tag(
      'span',
      array(
        'class' => 'phabricator-application-launch-description',
      ),
      phutil_escape_html($application->getShortDescription()));


    $count = 0;
    if ($this->status) {
      $content[] = '<span class="phabricator-application-status-block">';
      foreach ($this->status as $status) {
        $count += $status->getCount();
        $content[] = $status;
      }
      $content[] = '</span>';
    }

    if ($count) {
      $content[] = phutil_render_tag(
        'span',
        array(
          'class' => 'phabricator-application-launch-attention',
        ),
        phutil_escape_html($count));
    }

    return phutil_render_tag(
      'a',
      array(
        'class' => 'phabricator-application-launch-container',
        'style' => 'background-image: url('.$application->getIconURI().')',
        'href'  => $application->getBaseURI(),
      ),
      $this->renderSingleView($content));
  }
}
