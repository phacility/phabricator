<?php

final class DifferentialReviewerDatasource
  extends PhabricatorTypeaheadCompositeDatasource {

  public function getBrowseTitle() {
    return pht('Browse Reviewers');
  }

  public function getPlaceholderText() {
    return pht('Type a user, project, or package name...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorDifferentialApplication';
  }

  public function getComponentDatasources() {
    return array(
      new PhabricatorPeopleDatasource(),
      new PhabricatorProjectDatasource(),
      new PhabricatorOwnersPackageDatasource(),
      new DifferentialBlockingReviewerDatasource(),
    );
  }

}
