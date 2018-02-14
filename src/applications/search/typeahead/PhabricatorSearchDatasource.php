<?php

final class PhabricatorSearchDatasource
  extends PhabricatorTypeaheadCompositeDatasource {

  public function getBrowseTitle() {
    return pht('Browse Results');
  }

  public function getPlaceholderText() {
    return pht('Type an object name...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorSearchApplication';
  }

  public function getComponentDatasources() {
    $sources = id(new PhabricatorDatasourceEngine())
      ->getAllQuickSearchDatasources();

    // These results are always rendered in the full browse display mode, so
    // set the browse flag on all component sources.
    foreach ($sources as $source) {
      $source->setIsBrowse(true);
    }

    return $sources;
  }

}
