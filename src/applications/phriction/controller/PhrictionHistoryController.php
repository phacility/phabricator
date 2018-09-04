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

    $pager = id(new AphrontCursorPagerView())
      ->readFromRequest($request);

    $history = id(new PhrictionContentQuery())
      ->setViewer($viewer)
      ->withDocumentPHIDs(array($document->getPHID()))
      ->executeWithCursorPager($pager);

    $author_phids = mpull($history, 'getAuthorPHID');
    $handles = $viewer->loadHandles($author_phids);

    $max_version = (int)$document->getMaxVersion();
    $current_version = $document->getContent()->getVersion();

    $list = new PHUIObjectItemListView();
    $list->setFlush(true);
    foreach ($history as $content) {
      $slug_uri = PhrictionDocument::getSlugURI($document->getSlug());
      $version = $content->getVersion();

      $base_uri = new PhutilURI('/phriction/diff/'.$document->getID().'/');

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
          $color = 'indigo';
          break;
      }

      $version_uri = $slug_uri.'?v='.$version;

      $item = id(new PHUIObjectItemView())
        ->setHref($version_uri);

      if ($version > $current_version) {
        $icon = 'fa-spinner';
        $color = 'pink';
        $header = pht('Draft %d', $version);
      } else {
        $icon = 'fa-file-o';
        $header = pht('Version %d', $version);
      }

      if ($version == $current_version) {
        $item->setEffect('selected');
      }

      $item
        ->setHeader($header)
        ->setStatusIcon($icon.' '.$color);

      $description = $content->getDescription();
      if (strlen($description)) {
        $item->addAttribute($description);
      }

      $item->addIcon(
        null,
        phabricator_datetime($content->getDateCreated(), $viewer));

      $author_phid = $content->getAuthorPHID();
      $item->addByline($viewer->renderHandle($author_phid));

      $diff_uri = null;
      if ($version > 1) {
        $diff_uri = $base_uri
          ->alter('l', $version - 1)
          ->alter('r', $version);
      } else {
        $diff_uri = null;
      }

      if ($content->getVersion() != $max_version) {
        $compare_uri = $base_uri
          ->alter('l', $version)
          ->alter('r', $max_version);
      } else {
        $compare_uri = null;
      }

      $button_bar = id(new PHUIButtonBarView())
        ->addButton(
          id(new PHUIButtonView())
            ->setTag('a')
            ->setColor('grey')
            ->setIcon('fa-chevron-down')
            ->setDisabled(!$diff_uri)
            ->setHref($diff_uri)
            ->setText(pht('Diff')))
        ->addButton(
          id(new PHUIButtonView())
            ->setTag('a')
            ->setColor('grey')
            ->setIcon('fa-chevron-circle-up')
           ->setDisabled(!$compare_uri)
           ->setHref($compare_uri)
            ->setText(pht('Compare')));

      $item->setSideColumn($button_bar);

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
