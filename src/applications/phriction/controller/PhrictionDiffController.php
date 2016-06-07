<?php

final class PhrictionDiffController extends PhrictionController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $document = id(new PhrictionDocumentQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->needContent(true)
      ->executeOne();
    if (!$document) {
      return new Aphront404Response();
    }

    $current = $document->getContent();

    $l = $request->getInt('l');
    $r = $request->getInt('r');

    $ref = $request->getStr('ref');
    if ($ref) {
      list($l, $r) = explode(',', $ref);
    }

    $content = id(new PhrictionContent())->loadAllWhere(
      'documentID = %d AND version IN (%Ld)',
      $document->getID(),
      array($l, $r));
    $content = mpull($content, null, 'getVersion');

    $content_l = idx($content, $l, null);
    $content_r = idx($content, $r, null);

    if (!$content_l || !$content_r) {
      return new Aphront404Response();
    }

    $text_l = $content_l->getContent();
    $text_r = $content_r->getContent();

    $diff_view = id(new PhabricatorApplicationTransactionTextDiffDetailView())
      ->setOldText($text_l)
      ->setNewText($text_r);

    $changes = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Content Changes'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild(
        phutil_tag(
          'div',
          array(
            'class' => 'prose-diff-frame',
          ),
          $diff_view));

    require_celerity_resource('phriction-document-css');

    $slug = $document->getSlug();

    $revert_l = $this->renderRevertButton($content_l, $current);
    $revert_r = $this->renderRevertButton($content_r, $current);

    $crumbs = $this->buildApplicationCrumbs();
    $crumb_views = $this->renderBreadcrumbs($slug);
    foreach ($crumb_views as $view) {
      $crumbs->addCrumb($view);
    }

    $crumbs->addTextCrumb(
      pht('History'),
      PhrictionDocument::getSlugURI($slug, 'history'));

    $title = pht('Version %s vs %s', $l, $r);

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setHeaderIcon('fa-history');

    $crumbs->addTextCrumb($title, $request->getRequestURI());

    $comparison_table = $this->renderComparisonTable(
      array(
        $content_r,
        $content_l,
      ));

    $navigation_table = null;
    if ($l + 1 == $r) {
      $nav_l = ($l > 1);
      $nav_r = ($r != $current->getVersion());

      $uri = $request->getRequestURI();

      if ($nav_l) {
        $link_l = phutil_tag(
          'a',
          array(
            'href' => $uri->alter('l', $l - 1)->alter('r', $r - 1),
            'class' => 'button grey',
          ),
          pht("\xC2\xAB Previous Change"));
      } else {
        $link_l = phutil_tag(
          'a',
          array(
            'href' => '#',
            'class' => 'button grey disabled',
          ),
          pht('Original Change'));
      }

      $link_r = null;
      if ($nav_r) {
        $link_r = phutil_tag(
          'a',
          array(
            'href' => $uri->alter('l', $l + 1)->alter('r', $r + 1),
            'class' => 'button grey',
          ),
          pht("Next Change \xC2\xBB"));
      } else {
        $link_r = phutil_tag(
          'a',
          array(
            'href' => '#',
            'class' => 'button grey disabled',
          ),
          pht('Most Recent Change'));
      }

      $navigation_table = phutil_tag(
        'table',
        array('class' => 'phriction-history-nav-table'),
        phutil_tag('tr', array(), array(
          phutil_tag('td', array('class' => 'nav-prev'), $link_l),
          phutil_tag('td', array('class' => 'nav-next'), $link_r),
        )));
    }

    $output = hsprintf(
      '<div class="phriction-document-history-diff">'.
        '%s%s'.
        '<table class="phriction-revert-table">'.
          '<tr><td>%s</td><td>%s</td>'.
        '</table>'.
      '</div>',
      $comparison_table->render(),
      $navigation_table,
      $revert_l,
      $revert_r);

    $object_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Edits'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($output);

    $crumbs->setBorder(true);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(array(
        $object_box,
        $changes,
      ));

    return $this->newPage()
      ->setTitle(pht('Document History'))
      ->setCrumbs($crumbs)
      ->appendChild($view);

  }

  private function renderRevertButton(
    PhrictionContent $content,
    PhrictionContent $current) {

    $document_id = $content->getDocumentID();
    $version = $content->getVersion();

    $hidden_statuses = array(
      PhrictionChangeType::CHANGE_DELETE    => true, // Silly
      PhrictionChangeType::CHANGE_MOVE_AWAY => true, // Plain silly
      PhrictionChangeType::CHANGE_STUB      => true, // Utterly silly
    );
    if (isset($hidden_statuses[$content->getChangeType()])) {
      // Don't show an edit/revert button for changes which deleted, moved or
      // stubbed the content since it's silly.
      return null;
    }

    if ($content->getID() == $current->getID()) {
      return phutil_tag(
        'a',
        array(
          'href'  => '/phriction/edit/'.$document_id.'/',
          'class' => 'button grey',
        ),
        pht('Edit Current Version'));
    }


    return phutil_tag(
      'a',
      array(
        'href'  => '/phriction/edit/'.$document_id.'/?revert='.$version,
        'class' => 'button grey',
      ),
      pht('Revert to Version %s...', $version));
  }

  private function renderComparisonTable(array $content) {
    assert_instances_of($content, 'PhrictionContent');

    $viewer = $this->getViewer();

    $phids = mpull($content, 'getAuthorPHID');
    $handles = $this->loadViewerHandles($phids);

    $list = new PHUIObjectItemListView();

    $first = true;
    foreach ($content as $c) {
      $author = $handles[$c->getAuthorPHID()]->renderLink();
      $item = id(new PHUIObjectItemView())
        ->setHeader(pht('%s by %s, %s',
          PhrictionChangeType::getChangeTypeLabel($c->getChangeType()),
          $author,
          pht('Version %s', $c->getVersion())))
        ->addAttribute(pht('%s %s',
          phabricator_date($c->getDateCreated(), $viewer),
          phabricator_time($c->getDateCreated(), $viewer)));

      if ($c->getDescription()) {
        $item->addAttribute($c->getDescription());
      }

      if ($first == true) {
        $item->setStatusIcon('fa-file green');
        $first = false;
      } else {
        $item->setStatusIcon('fa-file red');
      }

      $list->addItem($item);
    }

    return $list;
  }

}
