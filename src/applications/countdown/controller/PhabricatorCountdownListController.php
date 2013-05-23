<?php

/**
 * @group countdown
 */
final class PhabricatorCountdownListController
  extends PhabricatorCountdownController {

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $pager = new AphrontCursorPagerView();
    $pager->readFromRequest($request);

    $query = id(new CountdownQuery())
      ->setViewer($user);

    $countdowns = $query->executeWithCursorPager($pager);

    $phids = mpull($countdowns, 'getAuthorPHID');
    $handles = $this->loadViewerHandles($phids);

    $rows = array();
    foreach ($countdowns as $timer) {
      $edit_button = null;
      $delete_button = null;
      if ($user->getIsAdmin() ||
          ($user->getPHID() == $timer->getAuthorPHID())) {
        $edit_button = phutil_tag(
          'a',
          array(
            'class' => 'small button grey',
            'href' => '/countdown/edit/'.$timer->getID().'/'
          ),
          pht('Edit'));

        $delete_button = javelin_tag(
          'a',
          array(
            'class' => 'small button grey',
            'href' => '/countdown/delete/'.$timer->getID().'/',
            'sigil' => 'workflow'
          ),
          pht('Delete'));
      }
      $rows[] = array(
        $timer->getID(),
        $handles[$timer->getAuthorPHID()]->renderLink(),
        phutil_tag(
          'a',
          array(
            'href' => '/countdown/'.$timer->getID().'/',
          ),
          $timer->getTitle()),
        phabricator_datetime($timer->getEpoch(), $user),
        $edit_button,
        $delete_button,
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        pht('ID'),
        pht('Author'),
        pht('Title'),
        pht('End Date'),
        '',
        ''
      ));

    $table->setColumnClasses(
      array(
        null,
        null,
        'wide pri',
        null,
        'action',
        'action',
      ));

    $panel = id(new AphrontPanelView())
      ->appendChild($table)
      ->setHeader(pht('Countdowns'))
      ->setNoBackground()
      ->appendChild($pager);

    $crumbs = $this
      ->buildApplicationCrumbs()
      ->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName(pht('All Countdowns'))
          ->setHref($this->getApplicationURI()));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $panel
      ),
      array(
        'title' => pht('Countdown'),
        'device' => true,
      ));
  }
}
