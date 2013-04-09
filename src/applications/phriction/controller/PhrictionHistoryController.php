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

    $list = new PhabricatorObjectItemListView();

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
          $color = 'blue';
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
          throw new Exception("Unknown change type!");
          break;
      }

      $item = id(new PhabricatorObjectItemView())
        ->setHeader(pht('%s by %s', $change_type, $author))
        ->setBarColor($color)
        ->addAttribute(
          phutil_tag(
            'a',
            array(
              'href' => $slug_uri.'?v='.$version,
            ),
            pht('Version %s', $version)))
        ->addAttribute(pht('%s %s',
          phabricator_date($content->getDateCreated(), $user),
          phabricator_time($content->getDateCreated(), $user)));

      if ($content->getDescription()) {
        $item->addAttribute($content->getDescription());
      }

      if ($vs_previous) {
        $item->addIcon('arrow_left', pht('Show Change'), $vs_previous);
      } else {
        $item->addIcon('arrow_left-grey',
          phutil_tag('em', array(), pht('No previous change')));
      }

      if ($vs_head) {
        $item->addIcon('merge', pht('Show Later Changes'), $vs_head);
      } else {
        $item->addIcon('merge-grey',
          phutil_tag('em', array(), pht('No later changes')));
      }

      $list->addItem($item);
    }

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

    $header = new PhabricatorHeaderView();
    $header->setHeader(pht('Document History for %s',
      phutil_tag(
        'a',
        array('href' => PhrictionDocument::getSlugURI($document->getSlug())),
        head($history)->getTitle())));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $header,
        $list,
        $pager,
      ),
      array(
        'title'     => pht('Document History'),
        'device'    => true,
        'dust'      => true,
      ));

  }

}
