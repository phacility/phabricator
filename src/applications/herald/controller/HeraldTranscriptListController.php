<?php

final class HeraldTranscriptListController extends HeraldController {

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $pager = new AphrontCursorPagerView();
    $pager->readFromRequest($request);

    $transcripts = id(new HeraldTranscriptQuery())
      ->setViewer($user)
      ->needPartialRecords(true)
      ->executeWithCursorPager($pager);

    // Render the table.
    $handles = array();
    if ($transcripts) {
      $phids = mpull($transcripts, 'getObjectPHID', 'getObjectPHID');
      $handles = $this->loadViewerHandles($phids);
    }

    $rows = array();
    foreach ($transcripts as $xscript) {
      $rows[] = array(
        phabricator_date($xscript->getTime(), $user),
        phabricator_time($xscript->getTime(), $user),
        $handles[$xscript->getObjectPHID()]->renderLink(),
        $xscript->getDryRun() ? pht('Yes') : '',
        number_format((int)(1000 * $xscript->getDuration())).' ms',
        phutil_tag(
          'a',
          array(
            'href' => '/herald/transcript/'.$xscript->getID().'/',
            'class' => 'button small grey',
          ),
          pht('View Transcript')),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        pht('Date'),
        pht('Time'),
        pht('Object'),
        pht('Dry Run'),
        pht('Duration'),
        pht('View'),
      ));
    $table->setColumnClasses(
      array(
        '',
        'right',
        'wide wrap',
        '',
        '',
        'action',
      ));

    // Render the whole page.
    $panel = new AphrontPanelView();
    $panel->setHeader(pht('Herald Transcripts'));
    $panel->appendChild($table);
    $panel->appendChild($pager);
    $panel->setNoBackground();

    $nav = $this->buildSideNavView();
    $nav->selectFilter('transcript');
    $nav->appendChild($panel);

    $crumbs = id($this->buildApplicationCrumbs())
      ->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName(pht('Transcripts')));
    $nav->setCrumbs($crumbs);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => pht('Herald Transcripts'),
        'device' => true,
      ));
  }

}
