<?php

final class DifferentialRepositoryDatasource
  extends PhabricatorTypeaheadCompositeDatasource {

  public function getBrowseTitle() {
    return pht('Browse Repositories');
  }

  public function getPlaceholderText() {
    return pht('Type a repository name or function...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorDifferentialApplication';
  }

  public function getComponentDatasources() {
    return array(
      new DiffusionTaggedRepositoriesFunctionDatasource(),
      new DiffusionRepositoryDatasource(),
    );
  }

}
