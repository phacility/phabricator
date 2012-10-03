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

final class PhabricatorApplicationDiffusion extends PhabricatorApplication {

  public function getShortDescription() {
    return 'Repository Browser';
  }

  public function getBaseURI() {
    return '/diffusion/';
  }

  public function getAutospriteName() {
    return 'diffusion';
  }

  public function getHelpURI() {
    return PhabricatorEnv::getDoclink('article/Diffusion_User_Guide.html');
  }

  public function getFactObjectsForAnalysis() {
    return array(
      new PhabricatorRepositoryCommit(),
    );
  }

  public function getRoutes() {
    return array(
      '/r(?P<callsign>[A-Z]+)(?P<commit>[a-z0-9]+)'
        => 'DiffusionCommitController',
      '/diffusion/' => array(
        '' => 'DiffusionHomeController',
        '(?P<callsign>[A-Z]+)/' => array(
          '' => 'DiffusionRepositoryController',

          'repository/(?P<dblob>.*)'    => 'DiffusionRepositoryController',
          'change/(?P<dblob>.*)'        => 'DiffusionChangeController',
          'history/(?P<dblob>.*)'       => 'DiffusionHistoryController',
          'browse/(?P<dblob>.*)'        => 'DiffusionBrowseController',
          'lastmodified/(?P<dblob>.*)'  => 'DiffusionLastModifiedController',
          'diff/'                       => 'DiffusionDiffController',
          'tags/(?P<dblob>.*)'          => 'DiffusionTagListController',
          'branches/(?P<dblob>.*)'      => 'DiffusionBranchTableController',

          'commit/(?P<commit>[a-z0-9]+)/branches/'
            => 'DiffusionCommitBranchesController',
          'commit/(?P<commit>[a-z0-9]+)/tags/'
            => 'DiffusionCommitTagsController',
          'commit/(?P<commit>[a-z0-9]+)/edit/'
            => 'DiffusionCommitEditController',
        ),
        'inline/' => array(
          'edit/(?P<phid>[^/]+)/'    => 'DiffusionInlineCommentController',
          'preview/(?P<phid>[^/]+)/' =>
            'DiffusionInlineCommentPreviewController',
        ),
        'services/' => array(
          'path/' => array(
            'complete/' => 'DiffusionPathCompleteController',
            'validate/' => 'DiffusionPathValidateController',
          ),
        ),
        'symbol/(?P<name>[^/]+)/' => 'DiffusionSymbolController',
        'external/' => 'DiffusionExternalController',
      ),
    );
  }

  public function getApplicationGroup() {
    return self::GROUP_CORE;
  }

  public function getApplicationOrder() {
    return 0.120;
  }

}

