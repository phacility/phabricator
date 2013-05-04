<?php

final class PhabricatorApplicationReleeph extends PhabricatorApplication {

  public function getName() {
    return pht('Releeph');
  }

  public function getShortDescription() {
    return pht('Release Branches');
  }

  public function getBaseURI() {
    return '/releeph/';
  }

  public function getAutospriteName() {
    return 'releeph';
  }

  public function getApplicationGroup() {
    return self::GROUP_ORGANIZATION;
  }

  public function isInstalled() {
    if (PhabricatorEnv::getEnvConfig('releeph.installed')) {
      return parent::isInstalled();
    }
    return false;
  }

  public function getRoutes() {
    return array(
      '/RQ(?P<requestID>[1-9]\d*)' => 'ReleephRequestViewController',
      '/releeph/' => array(
        '' => 'ReleephProjectListController',
        'project/' => array(
          '(?:(?P<filter>active|inactive)/)?' => 'ReleephProjectListController',
          'create/' => 'ReleephProjectCreateController',
          '(?P<projectID>[1-9]\d*)/' => array(
            '' => 'ReleephProjectViewController',
            'closedbranches/' => 'ReleephProjectViewController',
            'edit/' => 'ReleephProjectEditController',
            'cutbranch/' => 'ReleephBranchCreateController',
            'action/(?P<action>.+)/' => 'ReleephProjectActionController',
          ),
        ),
        'branch/' => array(
          'edit/(?P<branchID>[1-9]\d*)/' =>
            'ReleephBranchEditController',
          '(?P<action>close|re-open)/(?P<branchID>[1-9]\d*)/' =>
            'ReleephBranchAccessController',
          'preview/' => 'ReleephBranchNamePreviewController',

          // Left in, just in case the by-name stuff fails!
          '(?P<branchID>[^/]+)/' =>
            'ReleephBranchViewController',
        ),
        'request/' => array(
          '(?P<requestID>[1-9]\d*)/' => 'ReleephRequestViewController',
          'create/' => 'ReleephRequestCreateController',
          'differentialcreate/' => array(
            'D(?P<diffRevID>[1-9]\d*)' =>
              'ReleephRequestDifferentialCreateController',
          ),
          'edit/(?P<requestID>[1-9]\d*)/' =>
            'ReleephRequestEditController',
          'action/(?P<action>.+)/(?P<requestID>[1-9]\d*)/' =>
            'ReleephRequestActionController',
          'typeahead/' =>
            'ReleephRequestTypeaheadController',
        ),

        // Branch navigation made pretty, as it's the most common:
        '(?P<projectName>[^/]+)/(?P<branchName>[^/]+)/' => array(
          ''              => 'ReleephBranchViewController',
          'edit/'         => 'ReleephBranchEditController',
          'request/'      => 'ReleephRequestCreateController',
          '(?P<action>close|re-open)/' => 'ReleephBranchAccessController',
        ),
      )
    );
  }

}
