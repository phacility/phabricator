<?php

/**
 * @group phriction
 */
final class PhrictionHistoryController
  extends PhrictionController {

  private $slug;

  public function willProcessRequest(array $data) {
    $this->slug = $data['slug'];
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $document = id(new PhrictionDocument())->loadOneWhere(
      'slug = %s',
      PhabricatorSlug::normalize($this->slug));

    if (!$document) {
      return new Aphront404Response();
    }

    $current = id(new PhrictionContent())->load($document->getContentID());

    $pager = new AphrontPagerView();
    $pager->setOffset($request->getInt('page'));
    $pager->setURI($request->getRequestURI(), 'page');

    $history = id(new PhrictionContent())->loadAllWhere(
      'documentID = %d ORDER BY version DESC LIMIT %d, %d',
      $document->getID(),
      $pager->getOffset(),
      $pager->getPageSize() + 1);
    $history = $pager->sliceResults($history);

    $author_phids = mpull($history, 'getAuthorPHID');
    $handles = $this->loadViewerHandles($author_phids);

    $rows = array();
    foreach ($history as $content) {

      $slug_uri = PhrictionDocument::getSlugURI($document->getSlug());
      $version = $content->getVersion();

      $diff_uri = new PhutilURI('/phriction/diff/'.$document->getID().'/');

      $vs_previous = phutil_tag('em', array(), pht('Created'));
      if ($content->getVersion() != 1) {
        $uri = $diff_uri
          ->alter('l', $content->getVersion() - 1)
          ->alter('r', $content->getVersion());
        $vs_previous = phutil_tag(
          'a',
          array(
            'href' => $uri,
          ),
          pht('Show Change'));
      }

      $vs_head = phutil_tag('em', array(), pht('Current'));
      if ($content->getID() != $document->getContentID()) {
        $uri = $diff_uri
          ->alter('l', $content->getVersion())
          ->alter('r', $current->getVersion());

        $vs_head = phutil_tag(
          'a',
          array(
            'href' => $uri,
          ),
          pht('Show Later Changes'));
      }

      $change_type = PhrictionChangeType::getChangeTypeLabel(
        $content->getChangeType());

      $rows[] = array(
        phabricator_date($content->getDateCreated(), $user),
        phabricator_time($content->getDateCreated(), $user),
        phutil_tag(
          'a',
          array(
            'href' => $slug_uri.'?v='.$version,
          ),
          pht('Version %s', $version)),
        $handles[$content->getAuthorPHID()]->renderLink(),
        $change_type,
        $content->getDescription(),
        $vs_previous,
        $vs_head,
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        pht('Date'),
        pht('Time'),
        pht('Version'),
        pht('Author'),
        pht('Type'),
        pht('Description'),
        pht('Against Previous'),
        pht('Against Current'),
      ));
    $table->setColumnClasses(
      array(
        '',
        'right',
        'pri',
        '',
        '',
        'wide',
        '',
        '',
      ));

    $crumbs = $this->buildApplicationCrumbs();
    $crumb_views = $this->renderBreadcrumbs($document->getSlug());
    foreach ($crumb_views as $view) {
      $crumbs->addCrumb($view);
    }
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('History'))
        ->setHref(
          PhrictionDocument::getSlugURI($document->getSlug(), 'history')));

    $panel = new AphrontPanelView();
    $panel->setHeader(pht('Document History'));
    $panel->setNoBackground();
    $panel->appendChild($table);
    $panel->appendChild($pager);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $panel,
      ),
      array(
        'title'     => pht('Document History'),
        'device'    => true,
      ));

  }

}
