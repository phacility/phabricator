<?php

final class PhabricatorXHProfSampleSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('XHProf Samples');
  }

  public function getApplicationClassName() {
    return 'PhabricatorXHProfApplication';
  }

  public function newQuery() {
    return id(new PhabricatorXHProfSampleQuery());
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    return $query;
  }

  protected function buildCustomSearchFields() {
    return array();
  }

  protected function getURI($path) {
    return '/xhprof/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array(
      'all' => pht('All Samples'),
    );

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {
    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'all':
        return $query;
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  protected function renderResultList(
    array $samples,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($samples, 'PhabricatorXHProfSample');

    $viewer = $this->requireViewer();

    $list = new PHUIObjectItemListView();
    foreach ($samples as $sample) {
      $file_phid = $sample->getFilePHID();

      $item = id(new PHUIObjectItemView())
        ->setObjectName($sample->getID())
        ->setHeader($sample->getRequestPath())
        ->setHref($this->getApplicationURI('profile/'.$file_phid.'/'))
        ->addAttribute(
          number_format($sample->getUsTotal())." \xCE\xBCs");

      if ($sample->getController()) {
        $item->addAttribute($sample->getController());
      }

      $item->addAttribute($sample->getHostName());

      $rate = $sample->getSampleRate();
      if ($rate == 0) {
        $item->addIcon('flag-6', pht('Manual Run'));
      } else {
        $item->addIcon('flag-7', pht('Sampled (1/%d)', $rate));
      }

      $item->addIcon(
        'none',
        phabricator_datetime($sample->getDateCreated(), $viewer));

      $list->addItem($item);
    }

    $result = new PhabricatorApplicationSearchResultView();
    $result->setObjectList($list);

    return $result;
  }

}
