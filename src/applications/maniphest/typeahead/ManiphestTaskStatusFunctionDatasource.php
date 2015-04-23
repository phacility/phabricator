<?php

final class ManiphestTaskStatusFunctionDatasource
  extends PhabricatorTypeaheadCompositeDatasource {

  public function getBrowseTitle() {
    return pht('Browse Statuses');
  }

  public function getPlaceholderText() {
    return pht('Type a task status name or function...');
  }

  public function getComponentDatasources() {
    return array(
      new ManiphestTaskStatusDatasource(),
      new ManiphestTaskClosedStatusDatasource(),
      new ManiphestTaskOpenStatusDatasource(),
    );
  }

}
