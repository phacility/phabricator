<?php

final class PhabricatorDiffusionApplication extends PhabricatorApplication {

  public function getName() {
    return pht('Diffusion');
  }

  public function getShortDescription() {
    return pht('Host and Browse Repositories');
  }

  public function getBaseURI() {
    return '/diffusion/';
  }

  public function getIcon() {
    return 'fa-code';
  }

  public function isPinnedByDefault(PhabricatorUser $viewer) {
    return true;
  }

  public function getHelpDocumentationArticles(PhabricatorUser $viewer) {
    return array(
      array(
        'name' => pht('Diffusion User Guide'),
        'href' => PhabricatorEnv::getDoclink('Diffusion User Guide'),
      ),
    );
  }

  public function getFactObjectsForAnalysis() {
    return array(
      new PhabricatorRepositoryCommit(),
    );
  }

  public function getRemarkupRules() {
    return array(
      new DiffusionCommitRemarkupRule(),
      new DiffusionRepositoryRemarkupRule(),
      new DiffusionRepositoryByIDRemarkupRule(),
    );
  }

  public function getRoutes() {
    return array(
      '/(?:'.
        'r(?P<repositoryCallsign>[A-Z]+)'.
        '|'.
        'R(?P<repositoryID>[1-9]\d*):'.
      ')(?P<commit>[a-f0-9]+)'
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
        '(?:'.
          '(?P<repositoryCallsign>[A-Z]+)'.
          '|'.
          '(?P<repositoryID>[1-9]\d*)'.
        ')/' => array(
          '' => 'DiffusionRepositoryController',

          'repository/(?P<dblob>.*)'    => 'DiffusionRepositoryController',
          'change/(?P<dblob>.*)'        => 'DiffusionChangeController',
          'history/(?P<dblob>.*)'       => 'DiffusionHistoryController',
          'browse/(?P<dblob>.*)'        => 'DiffusionBrowseController',
          'lastmodified/(?P<dblob>.*)'  => 'DiffusionLastModifiedController',
          'diff/'                       => 'DiffusionDiffController',
          'tags/(?P<dblob>.*)'          => 'DiffusionTagListController',
          'branches/(?P<dblob>.*)'      => 'DiffusionBranchTableController',
          'refs/(?P<dblob>.*)'          => 'DiffusionRefTableController',
          'lint/(?P<dblob>.*)'          => 'DiffusionLintController',
          'commit/(?P<commit>[a-z0-9]+)/branches/'
            => 'DiffusionCommitBranchesController',
          'commit/(?P<commit>[a-z0-9]+)/tags/'
            => 'DiffusionCommitTagsController',
          'commit/(?P<commit>[a-z0-9]+)/edit/'
            => 'DiffusionCommitEditController',
          'manage/(?:(?P<panel>[^/]+)/)?'
            => 'DiffusionRepositoryManageController',
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
            'storage/' => 'DiffusionRepositoryEditStorageController',
            'delete/' => 'DiffusionRepositoryEditDeleteController',
            'hosting/' => 'DiffusionRepositoryEditHostingController',
            '(?P<serve>serve)/' => 'DiffusionRepositoryEditHostingController',
            'update/' => 'DiffusionRepositoryEditUpdateController',
            'symbol/' => 'DiffusionRepositorySymbolsController',
            'staging/' => 'DiffusionRepositoryEditStagingController',
            'automation/' => 'DiffusionRepositoryEditAutomationController',
            'testautomation/' => 'DiffusionRepositoryTestAutomationController',
          ),
          'pathtree/(?P<dblob>.*)' => 'DiffusionPathTreeController',
          'mirror/' => array(
            'edit/(?:(?P<id>\d+)/)?' => 'DiffusionMirrorEditController',
            'delete/(?P<id>\d+)/' => 'DiffusionMirrorDeleteController',
          ),
        ),

        // NOTE: This must come after the rule above; it just gives us a
        // catch-all for serving repositories over HTTP. We must accept
        // requests without the trailing "/" because SVN commands don't
        // necessarily include it.
        '(?:(?P<repositoryCallsign>[A-Z]+)|(?P<repositoryID>[1-9]\d*))'.
          '(?:/.*)?'
          => 'DiffusionRepositoryDefaultController',

        'inline/' => array(
          'edit/(?P<phid>[^/]+)/' => 'DiffusionInlineCommentController',
          'preview/(?P<phid>[^/]+)/'
            => 'DiffusionInlineCommentPreviewController',
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

  public function getApplicationOrder() {
    return 0.120;
  }

  protected function getCustomCapabilities() {
    return array(
      DiffusionDefaultViewCapability::CAPABILITY => array(
        'template' => PhabricatorRepositoryRepositoryPHIDType::TYPECONST,
        'capability' => PhabricatorPolicyCapability::CAN_VIEW,
      ),
      DiffusionDefaultEditCapability::CAPABILITY => array(
        'default' => PhabricatorPolicies::POLICY_ADMIN,
        'template' => PhabricatorRepositoryRepositoryPHIDType::TYPECONST,
        'capability' => PhabricatorPolicyCapability::CAN_EDIT,
      ),
      DiffusionDefaultPushCapability::CAPABILITY => array(
        'template' => PhabricatorRepositoryRepositoryPHIDType::TYPECONST,
      ),
      DiffusionCreateRepositoriesCapability::CAPABILITY => array(
        'default' => PhabricatorPolicies::POLICY_ADMIN,
      ),
    );
  }

  public function getMailCommandObjects() {
    return array(
      'commit' => array(
        'name' => pht('Email Commands: Commits'),
        'header' => pht('Interacting with Commits'),
        'object' => new PhabricatorRepositoryCommit(),
        'summary' => pht(
          'This page documents the commands you can use to interact with '.
          'commits and audits in Diffusion.'),
      ),
    );
  }

  public function getApplicationSearchDocumentTypes() {
    return array(
      PhabricatorRepositoryCommitPHIDType::TYPECONST,
    );
  }

}
