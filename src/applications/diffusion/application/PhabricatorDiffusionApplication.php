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
      array(
        'name' => pht('Audit User Guide'),
        'href' => PhabricatorEnv::getDoclink('Audit User Guide'),
      ),
    );
  }

  public function getRemarkupRules() {
    return array(
      new DiffusionCommitRemarkupRule(),
      new DiffusionRepositoryRemarkupRule(),
      new DiffusionRepositoryByIDRemarkupRule(),
      new DiffusionSourceLinkRemarkupRule(),
    );
  }

  public function getRoutes() {
    $repository_routes = array(
      '/' => array(
        '' => 'DiffusionRepositoryController',
        'repository/(?P<dblob>.*)' => 'DiffusionRepositoryController',
        'change/(?P<dblob>.*)' => 'DiffusionChangeController',
        'clone/' => 'DiffusionCloneController',
        'history/(?P<dblob>.*)' => 'DiffusionHistoryController',
        'browse/(?P<dblob>.*)' => 'DiffusionBrowseController',
        'document/(?P<dblob>.*)'
          => 'DiffusionDocumentController',
        'blame/(?P<dblob>.*)'
          => 'DiffusionBlameController',
        'lastmodified/(?P<dblob>.*)' => 'DiffusionLastModifiedController',
        'diff/' => 'DiffusionDiffController',
        'tags/(?P<dblob>.*)' => 'DiffusionTagListController',
        'branches/(?P<dblob>.*)' => 'DiffusionBranchTableController',
        'refs/(?P<dblob>.*)' => 'DiffusionRefTableController',
        'lint/(?P<dblob>.*)' => 'DiffusionLintController',
        'commit/(?P<commit>[a-z0-9]+)' => array(
          '/?' => 'DiffusionCommitController',
          '/branches/' => 'DiffusionCommitBranchesController',
          '/tags/' => 'DiffusionCommitTagsController',
        ),
        'compare/' => 'DiffusionCompareController',
        'manage/(?:(?P<panel>[^/]+)/)?'
          => 'DiffusionRepositoryManagePanelsController',
        'uri/' => array(
          'view/(?P<id>[0-9]\d*)/' => 'DiffusionRepositoryURIViewController',
          'disable/(?P<id>[0-9]\d*)/'
            => 'DiffusionRepositoryURIDisableController',
          $this->getEditRoutePattern('edit/')
            => 'DiffusionRepositoryURIEditController',
          'credential/(?P<id>[0-9]\d*)/(?P<action>edit|remove)/'
            => 'DiffusionRepositoryURICredentialController',
        ),
        'edit/' => array(
          'activate/' => 'DiffusionRepositoryEditActivateController',
          'dangerous/' => 'DiffusionRepositoryEditDangerousController',
          'enormous/' => 'DiffusionRepositoryEditEnormousController',
          'delete/' => 'DiffusionRepositoryEditDeleteController',
          'update/' => 'DiffusionRepositoryEditUpdateController',
          'publish/' => 'DiffusionRepositoryEditPublishingController',
          'testautomation/' => 'DiffusionRepositoryTestAutomationController',
        ),
        'pathtree/(?P<dblob>.*)' => 'DiffusionPathTreeController',
      ),

      // NOTE: This must come after the rules above; it just gives us a
      // catch-all for serving repositories over HTTP. We must accept requests
      // without the trailing "/" because SVN commands don't necessarily
      // include it.
      '(?:/.*)?' => 'DiffusionRepositoryDefaultController',
    );

    return array(
      '/(?:'.
        'r(?P<repositoryCallsign>[A-Z]+)'.
        '|'.
        'R(?P<repositoryID>[1-9]\d*):'.
      ')(?P<commit>[a-f0-9]+)'
        => 'DiffusionCommitController',

      '/source/(?P<repositoryShortName>[^/]+)'
        => $repository_routes,

      '/diffusion/' => array(
        $this->getQueryRoutePattern()
          => 'DiffusionRepositoryListController',
        $this->getEditRoutePattern('edit/') =>
          'DiffusionRepositoryEditController',
        'pushlog/' => array(
          $this->getQueryRoutePattern() => 'DiffusionPushLogListController',
          'view/(?P<id>\d+)/' => 'DiffusionPushEventViewController',
        ),
        'synclog/' => array(
          $this->getQueryRoutePattern() => 'DiffusionSyncLogListController',
        ),
        'pulllog/' => array(
          $this->getQueryRoutePattern() => 'DiffusionPullLogListController',
        ),
        '(?P<repositoryCallsign>[A-Z]+)' => $repository_routes,
        '(?P<repositoryID>[1-9]\d*)' => $repository_routes,

        'identity/' => array(
          $this->getQueryRoutePattern() =>
            'DiffusionIdentityListController',
          $this->getEditRoutePattern('edit/') =>
            'DiffusionIdentityEditController',
          'view/(?P<id>[^/]+)/' =>
            'DiffusionIdentityViewController',
        ),

        'inline/' => array(
          'edit/(?P<phid>[^/]+)/' => 'DiffusionInlineCommentController',
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

        'commit/' => array(
          $this->getQueryRoutePattern() =>
            'DiffusionCommitListController',
          $this->getEditRoutePattern('edit/') =>
            'DiffusionCommitEditController',
        ),
        'picture/(?P<id>[0-9]\d*)/'
          => 'DiffusionRepositoryProfilePictureController',
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
