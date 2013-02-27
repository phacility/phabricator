<?php

final class PhabricatorApplicationDiffusion extends PhabricatorApplication {

  public function getShortDescription() {
    return 'Repository Browser';
  }

  public function getBaseURI() {
    return '/diffusion/';
  }

  public function getIconName() {
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

  public function getEventListeners() {
    return array(
      new DiffusionPeopleMenuEventListener()
    );
  }

  public function getRemarkupRules() {
    return array(
      new DiffusionRemarkupRule(),
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
          'lint/(?P<dblob>.*)'          => 'DiffusionLintController',

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
        'lint/' => 'DiffusionLintController',
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

