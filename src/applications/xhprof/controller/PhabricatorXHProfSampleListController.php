<?php

final class PhabricatorXHProfSampleListController
  extends PhabricatorXHProfController {

  private $view;

  public function willProcessRequest(array $data) {
    $this->view = idx($data, 'view', 'all');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $pager = new AphrontPagerView();
    $pager->setOffset($request->getInt('page'));

    switch ($this->view) {
      case 'sampled':
        $clause = 'sampleRate > 0';
        $show_type = false;
        break;
      case 'my-runs':
        $clause = qsprintf(
          id(new PhabricatorXHProfSample())->establishConnection('r'),
          'sampleRate = 0 AND userPHID = %s',
          $request->getUser()->getPHID());
        $show_type = false;
        break;
      case 'manual':
        $clause = 'sampleRate = 0';
        $show_type = false;
        break;
      case 'all':
      default:
        $clause = '1 = 1';
        $show_type = true;
        break;
    }

    $samples = id(new PhabricatorXHProfSample())->loadAllWhere(
      '%Q ORDER BY id DESC LIMIT %d, %d',
      $clause,
      $pager->getOffset(),
      $pager->getPageSize() + 1);

    $samples = $pager->sliceResults($samples);
    $pager->setURI($request->getRequestURI(), 'page');

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
        phabricator_datetime($sample->getDateCreated(), $user));

      $list->addItem($item);
    }

    $list->setPager($pager);
    $list->setNoDataString(pht('There are no profiling samples.'));

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('XHProf Samples'));

    return $this->buildApplicationPage(
      array($crumbs, $list),
      array(
        'title' => pht('XHProf Samples'),
      ));

  }
}
