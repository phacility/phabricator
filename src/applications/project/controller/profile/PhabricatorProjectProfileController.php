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

class PhabricatorProjectProfileController
  extends PhabricatorProjectController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {

    $project = id(new PhabricatorProject())->load($this->id);
    if (!$project) {
      return new Aphront404Response();
    }
    $profile = id(new PhabricatorProjectProfile())->loadOneWhere(
      'projectPHID = %s',
      $project->getPHID());
    if (!$profile) {
      $profile = new PhabricatorProjectProfile();
    }

    require_celerity_resource('phabricator-profile-css');

    $src_phid = $profile->getProfileImagePHID();
    $src = PhabricatorFileURI::getViewURIForPHID($src_phid);

    $picture = phutil_render_tag(
      'img',
      array(
        'class' => 'profile-image',
        'src'   => $src,
      ));

    $links =
      '<ul class="profile-nav-links">'.
        '<li><a href="/project/edit/'.$project->getID().'/">'.
          'Edit Project</a></li>'.
        '<li><a href="/project/affiliation/'.$project->getID().'/">'.
          'Edit Affiliation</a></li>'.
      '</ul>';

    $blurb = nonempty(
      $profile->getBlurb(),
      '//Nothing is known about this elusive project.//');

    $factory = new DifferentialMarkupEngineFactory();
    $engine = $factory->newDifferentialCommentMarkupEngine();
    $blurb = $engine->markupText($blurb);


    $affiliations = id(new PhabricatorProjectAffiliation())->loadAllWhere(
      'projectPHID = %s ORDER BY IF(status = "former", 1, 0), dateCreated',
      $project->getPHID());

    $phids = array_merge(
      array($project->getAuthorPHID()),
      mpull($affiliations, 'getUserPHID'));
    $handles = id(new PhabricatorObjectHandleData($phids))
      ->loadHandles();

    $affiliated = array();
    foreach ($affiliations as $affiliation) {
      $user = $handles[$affiliation->getUserPHID()]->renderLink();
      $role = phutil_escape_html($affiliation->getRole());

      $status = null;
      if ($affiliation->getStatus() == 'former') {
        $role = '<em>Former '.$role.'</em>';
      }

      $affiliated[] = '<li>'.$user.' &mdash; '.$role.$status.'</li>';
    }
    if ($affiliated) {
      $affiliated = '<ul>'.implode("\n", $affiliated).'</ul>';
    } else {
      $affiliated = '<p><em>No one is affiliated with this project.</em></p>';
    }

    $timestamp = phabricator_format_timestamp($project->getDateCreated());

    $content =
      '<div class="phabricator-profile-info-group">
        <h1 class="phabricator-profile-info-header">Basic Information</h1>
        <div class="phabricator-profile-info-pane">
          <table class="phabricator-profile-info-table">
            <tr>
              <th>Creator</th>
              <td>'.$handles[$project->getAuthorPHID()]->renderLink().'</td>
            </tr>
            <tr>
              <th>Created</th>
              <td>'.$timestamp.'</td>
            </tr>
            <tr>
              <th>PHID</th>
              <td>'.phutil_escape_html($project->getPHID()).'</td>
            </tr>
            <tr>
              <th>Blurb</th>
              <td>'.$blurb.'</td>
            </tr>
          </table>
        </div>
      </div>';

    $content .=
      '<div class="phabricator-profile-info-group">
        <h1 class="phabricator-profile-info-header">Resources</h1>
        <div class="phabricator-profile-info-pane">'.
        $affiliated.
        '</div>
      </div>';


    $profile_markup =
      '<table class="phabricator-profile-master-layout">
        <tr>
          <td class="phabricator-profile-navigation">'.
            '<h1>'.phutil_escape_html($project->getName()).'</h1>'.
            '<hr />'.
            $picture.
            '<hr />'.
            $links.
            '<hr />'.
          '</td>
          <td class="phabricator-profile-content">'.
          $content.
          '</td>
        </tr>
      </table>';

    return $this->buildStandardPageResponse(
      $profile_markup,
      array(
        'title' => $project->getName(),
      ));
  }

}
