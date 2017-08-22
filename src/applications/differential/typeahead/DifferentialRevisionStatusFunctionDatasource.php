<?php

final class DifferentialRevisionStatusFunctionDatasource
  extends PhabricatorTypeaheadCompositeDatasource {

  public function getBrowseTitle() {
    return pht('Browse Statuses');
  }

  public function getPlaceholderText() {
    return pht('Type a revision status name or function...');
  }

  public function getComponentDatasources() {
    return array(
      new DifferentialRevisionStatusDatasource(),
      new DifferentialRevisionClosedStatusDatasource(),
      new DifferentialRevisionOpenStatusDatasource(),
    );
  }

}
