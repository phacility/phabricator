<?php

final class PhabricatorContentSourceModule
  extends PhabricatorConfigModule {

  public function getModuleKey() {
    return 'contentsource';
  }

  public function getModuleName() {
    return pht('Content Sources');
  }

  public function renderModuleStatus(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $sources = PhabricatorContentSource::getAllContentSources();
    ksort($sources);

    $rows = array();
    foreach ($sources as $source) {
      $rows[] = array(
        $source->getSourceTypeConstant(),
        get_class($source),
        $source->getSourceName(),
        $source->getSourceDescription(),
      );
    }

    return id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          pht('Key'),
          pht('Class'),
          pht('Source'),
          pht('Description'),
        ))
      ->setColumnClasses(
        array(
          null,
          null,
          'pri',
          'wide',
        ));

  }

}
