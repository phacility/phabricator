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
        ->setHeader($sample->getDisplayName())
        ->setHref($sample->getURI());

      $us_total = $sample->getUsTotal();
      if ($us_total) {
        $item->addAttribute(pht("%s \xCE\xBCs", new PhutilNumber($us_total)));
      }

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

    return $this->newResultView()
      ->setObjectList($list);
  }


  private function newResultView($content = null) {
    // If we aren't rendering a dashboard panel, activate global drag-and-drop
    // so you can import profiles by dropping them into the list.

    if (!$this->isPanelContext()) {
      $drop_upload = id(new PhabricatorGlobalUploadTargetView())
        ->setViewer($this->requireViewer())
        ->setHintText("\xE2\x87\xAA ".pht('Drop .xhprof Files to Import'))
        ->setSubmitURI('/xhprof/import/drop/')
        ->setViewPolicy(PhabricatorPolicies::POLICY_NOONE);

      $content = array(
        $drop_upload,
        $content,
      );
    }

    return id(new PhabricatorApplicationSearchResultView())
      ->setContent($content);
  }

}
