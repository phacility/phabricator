<?php

final class PhabricatorTypeaheadUserParameterizedDatasource
  extends PhabricatorTypeaheadCompositeDatasource {

  public function getPlaceholderText() {
    return pht('Type a username or selector...');
  }

  public function getComponentDatasources() {
    $sources = array(
      new PhabricatorViewerDatasource(),
      new PhabricatorPeopleDatasource(),
      new PhabricatorProjectMembersDatasource(),
    );

    return $sources;
  }

}
