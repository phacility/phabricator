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

    $text_l = phutil_utf8_hard_wrap($text_l, 80);
    $text_l = implode("\n", $text_l);
    $text_r = phutil_utf8_hard_wrap($text_r, 80);
    $text_r = implode("\n", $text_r);

    $engine = new PhabricatorDifferenceEngine();
    $changeset = $engine->generateChangesetFromFileContent($text_l, $text_r);

    $changeset->setFilename($content_r->getTitle());

    $changeset->setOldProperties(
      array(
        'Title'   => $content_l->getTitle(),
      ));
    $changeset->setNewProperties(
      array(
        'Title'   => $content_r->getTitle(),
      ));

    $whitespace_mode = DifferentialChangesetParser::WHITESPACE_SHOW_ALL;

    $parser = id(new DifferentialChangesetParser())
      ->setUser($viewer)
      ->setChangeset($changeset)
      ->setRenderingReference("{$l},{$r}");

    $parser->readParametersFromRequest($request);
    $parser->setWhitespaceMode($whitespace_mode);

    $engine = new PhabricatorMarkupEngine();
    $engine->setViewer($viewer);
    $engine->process();
    $parser->setMarkupEngine($engine);

    $spec = $request->getStr('range');
    list($range_s, $range_e, $mask) =
      DifferentialChangesetParser::parseRangeSpecification($spec);

    $parser->setRange($range_s, $range_e);
    $parser->setMask($mask);

    if ($request->isAjax()) {
      return id(new PhabricatorChangesetResponse())
        ->setRenderedChangeset($parser->renderChangeset());
    }

    $changes = id(new DifferentialChangesetListView())
      ->setUser($this->getViewer())
      ->setChangesets(array($changeset))
      ->setVisibleChangesets(array($changeset))
      ->setRenderingReferences(array("{$l},{$r}"))
      ->setRenderURI('/phriction/diff/'.$document->getID().'/')
      ->setTitle(pht('Changes'))
      ->setParser($parser);

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
      ->setTall(true);

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
            'class' => 'button simple',
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
            'class' => 'button simple',
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
      ->setHeader($header)
      ->appendChild($output);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $object_box,
        $changes,
      ),
      array(
        'title'     => pht('Document History'),
      ));

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
          'class' => 'button simple',
        ),
        pht('Edit Current Version'));
    }


    return phutil_tag(
      'a',
      array(
        'href'  => '/phriction/edit/'.$document_id.'/?revert='.$version,
        'class' => 'button simple',
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
