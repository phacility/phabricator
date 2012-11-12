<?php

final class PhabricatorXHProfSampleListController
  extends PhabricatorXHProfController {

  private $view;

  public function willProcessRequest(array $data) {
    $this->view = $data['view'];
  }

  public function processRequest() {
    $request = $this->getRequest();

    $pager = new AphrontPagerView();
    $pager->setOffset($request->getInt('page'));

    switch ($this->view) {
      case 'sampled':
        $clause = '`sampleRate` > 0';
        $show_type = false;
        break;
      case 'my-runs':
        $clause = qsprintf(
          id(new PhabricatorXHProfSample())->establishConnection('r'),
          '`sampleRate` = 0 AND `userPHID` = %s',
          $request->getUser()->getPHID());
        $show_type = false;
        break;
      case 'manual':
        $clause = '`sampleRate` = 0';
        $show_type = false;
        break;
      case 'all':
      default:
        $clause = '1 = 1';
        $show_type = true;
        break;
    }

    $samples = id(new PhabricatorXHProfSample())->loadAllWhere(
      '%Q ORDER BY dateCreated DESC LIMIT %d, %d',
      $clause,
      $pager->getOffset(),
      $pager->getPageSize() + 1);

    $samples = $pager->sliceResults($samples);
    $pager->setURI($request->getRequestURI(), 'page');

    $table = new PhabricatorXHProfSampleListView();
    $table->setUser($request->getUser());
    $table->setSamples($samples);
    $table->setShowType($show_type);

    $panel = new AphrontPanelView();
    $panel->setHeader('XHProf Samples');
    $panel->appendChild($table);
    $panel->appendChild($pager);

    return $this->buildStandardPageResponse(
      $panel,
      array('title' => 'XHProf Samples'));

  }
}
