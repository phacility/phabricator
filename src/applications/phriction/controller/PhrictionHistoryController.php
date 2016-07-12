<?php

final class PhrictionHistoryController
  extends PhrictionController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $slug = $request->getURIData('slug');

    $document = id(new PhrictionDocumentQuery())
      ->setViewer($viewer)
      ->withSlugs(array(PhabricatorSlug::normalize($slug)))
      ->needContent(true)
      ->executeOne();
    if (!$document) {
      return new Aphront404Response();
    }

    $current = $document->getContent();

    $pager = new PHUIPagerView();
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

    $list = new PHUIObjectItemListView();
    $list->setFlush(true);

    foreach ($history as $content) {

      $author = $handles[$content->getAuthorPHID()]->renderLink();
      $slug_uri = PhrictionDocument::getSlugURI($document->getSlug());
      $version = $content->getVersion();

      $diff_uri = new PhutilURI('/phriction/diff/'.$document->getID().'/');

      $vs_previous = null;
      if ($content->getVersion() != 1) {
        $vs_previous = $diff_uri
          ->alter('l', $content->getVersion() - 1)
          ->alter('r', $content->getVersion());
      }

      $vs_head = null;
      if ($content->getID() != $document->getContentID()) {
        $vs_head = $diff_uri
          ->alter('l', $content->getVersion())
          ->alter('r', $current->getVersion());
      }

      $change_type = PhrictionChangeType::getChangeTypeLabel(
        $content->getChangeType());
      switch ($content->getChangeType()) {
        case PhrictionChangeType::CHANGE_DELETE:
          $color = 'red';
          break;
        case PhrictionChangeType::CHANGE_EDIT:
          $color = 'lightbluetext';
          break;
        case PhrictionChangeType::CHANGE_MOVE_HERE:
            $color = 'yellow';
          break;
        case PhrictionChangeType::CHANGE_MOVE_AWAY:
            $color = 'orange';
          break;
        case PhrictionChangeType::CHANGE_STUB:
          $color = 'green';
          break;
        default:
          throw new Exception(pht('Unknown change type!'));
          break;
      }

      $item = id(new PHUIObjectItemView())
        ->setHeader(pht('%s by %s', $change_type, $author))
        ->setStatusIcon('fa-file '.$color)
        ->addAttribute(
          phutil_tag(
            'a',
            array(
              'href' => $slug_uri.'?v='.$version,
            ),
            pht('Version %s', $version)))
        ->addAttribute(pht('%s %s',
          phabricator_date($content->getDateCreated(), $viewer),
          phabricator_time($content->getDateCreated(), $viewer)));

      if ($content->getDescription()) {
        $item->addAttribute($content->getDescription());
      }

      if ($vs_previous) {
        $item->addIcon(
          'fa-reply',
          pht('Show Change'),
          array(
            'href' => $vs_previous,
          ));
      } else {
        $item->addIcon(
          'fa-reply grey',
          phutil_tag('em', array(), pht('No previous change')));
      }

      if ($vs_head) {
        $item->addIcon(
          'fa-reply-all',
          pht('Show Later Changes'),
          array(
            'href' => $vs_head,
          ));
      } else {
        $item->addIcon(
          'fa-reply-all grey',
          phutil_tag('em', array(), pht('No later changes')));
      }

      $list->addItem($item);
    }

    $crumbs = $this->buildApplicationCrumbs();
    $crumb_views = $this->renderBreadcrumbs($document->getSlug());
    foreach ($crumb_views as $view) {
      $crumbs->addCrumb($view);
    }
    $crumbs->addTextCrumb(
      pht('History'),
      PhrictionDocument::getSlugURI($document->getSlug(), 'history'));
    $crumbs->setBorder(true);

    $header = new PHUIHeaderView();
    $header->setHeader(phutil_tag(
        'a',
        array('href' => PhrictionDocument::getSlugURI($document->getSlug())),
        head($history)->getTitle()));
    $header->setSubheader(pht('Document History'));

    $obj_box = id(new PHUIObjectBoxView())
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setObjectList($list);

    $pager = id(new PHUIBoxView())
      ->addClass('ml')
      ->appendChild($pager);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Document History: %s', head($history)->getTitle()))
      ->setHeaderIcon('fa-history');

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(array(
        $obj_box,
        $pager,
      ));

    $title = pht('Document History');

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);

  }

}
