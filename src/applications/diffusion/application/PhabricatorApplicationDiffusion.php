<?php

final class PhabricatorApplicationDiffusion extends PhabricatorApplication {

  public function getShortDescription() {
    return pht('Repository Browser');
  }

  public function getBaseURI() {
    return '/diffusion/';
  }

  public function getIconName() {
    return 'diffusion';
  }

  public function getHelpURI() {
    return PhabricatorEnv::getDoclink('Diffusion User Guide');
  }

  public function getFactObjectsForAnalysis() {
    return array(
      new PhabricatorRepositoryCommit(),
    );
  }

  public function getEventListeners() {
    return array(
      new DiffusionHovercardEventListener(),
    );
  }

  public function getRemarkupRules() {
    return array(
      new DiffusionRepositoryRemarkupRule(),
      new DiffusionCommitRemarkupRule(),
    );
  }

  public function getRoutes() {
    return array(
      '/r(?P<callsign>[A-Z]+)(?P<commit>[a-z0-9]+)'
        => 'DiffusionCommitController',
      '/diffusion/' => array(
        '(?:query/(?P<queryKey>[^/]+)/)?'
          => 'DiffusionRepositoryListController',
        'new/' => 'DiffusionRepositoryNewController',
        '(?P<edit>create)/' => 'DiffusionRepositoryCreateController',
        '(?P<edit>import)/' => 'DiffusionRepositoryCreateController',
        'pushlog/' => array(
          '(?:query/(?P<queryKey>[^/]+)/)?' => 'DiffusionPushLogListController',
          'view/(?P<id>\d+)/' => 'DiffusionPushEventViewController',
        ),
        '(?P<callsign>[A-Z]+)/' => array(
          '' => 'DiffusionRepositoryController',

          'repository/(?P<dblob>.*)'    => 'DiffusionRepositoryController',
          'change/(?P<dblob>.*)'        => 'DiffusionChangeController',
          'history/(?P<dblob>.*)'       => 'DiffusionHistoryController',
          'browse/(?P<dblob>.*)'        => 'DiffusionBrowseMainController',
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
          'edit/' => array(
            '' => 'DiffusionRepositoryEditMainController',
            'basic/' => 'DiffusionRepositoryEditBasicController',
            'encoding/' => 'DiffusionRepositoryEditEncodingController',
            'activate/' => 'DiffusionRepositoryEditActivateController',
            'dangerous/' => 'DiffusionRepositoryEditDangerousController',
            'branches/' => 'DiffusionRepositoryEditBranchesController',
            'subversion/' => 'DiffusionRepositoryEditSubversionController',
            'actions/' => 'DiffusionRepositoryEditActionsController',
            '(?P<edit>remote)/' => 'DiffusionRepositoryCreateController',
            '(?P<edit>policy)/' => 'DiffusionRepositoryCreateController',
            'local/' => 'DiffusionRepositoryEditLocalController',
            'delete/' => 'DiffusionRepositoryEditDeleteController',
            'hosting/' => 'DiffusionRepositoryEditHostingController',
            '(?P<serve>serve)/' => 'DiffusionRepositoryEditHostingController',
          ),
          'mirror/' => array(
            'edit/(?:(?P<id>\d+)/)?' => 'DiffusionMirrorEditController',
            'delete/(?P<id>\d+)/' => 'DiffusionMirrorDeleteController',
          ),
        ),

        // NOTE: This must come after the rule above; it just gives us a
        // catch-all for serving repositories over HTTP. We must accept
        // requests without the trailing "/" because SVN commands don't
        // necessarily include it.
        '(?P<callsign>[A-Z]+)(/|$).*' => 'DiffusionRepositoryDefaultController',

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

  protected function getCustomCapabilities() {
    return array(
      DiffusionCapabilityDefaultView::CAPABILITY => array(
      ),
      DiffusionCapabilityDefaultEdit::CAPABILITY => array(
        'default' => PhabricatorPolicies::POLICY_ADMIN,
      ),
      DiffusionCapabilityDefaultPush::CAPABILITY => array(
      ),
      DiffusionCapabilityCreateRepositories::CAPABILITY => array(
        'default' => PhabricatorPolicies::POLICY_ADMIN,
      ),
    );
  }

}
