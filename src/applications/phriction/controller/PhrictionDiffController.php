<?php

/**
 * @group phriction
 */
final class PhrictionDiffController
  extends PhrictionController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $document = id(new PhrictionDocument())->load($this->id);
    if (!$document) {
      return new Aphront404Response();
    }

    $current = id(new PhrictionContent())->load($document->getContentID());

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

    $changeset->setOldProperties(
      array(
        'Title'   => $content_l->getTitle(),
      ));
    $changeset->setNewProperties(
      array(
        'Title'   => $content_r->getTitle(),
      ));

    $whitespace_mode = DifferentialChangesetParser::WHITESPACE_SHOW_ALL;

    $parser = new DifferentialChangesetParser();
    $parser->setChangeset($changeset);
    $parser->setRenderingReference("{$l},{$r}");
    $parser->setWhitespaceMode($whitespace_mode);

    $engine = new PhabricatorMarkupEngine();
    $engine->setViewer($user);
    $engine->process();
    $parser->setMarkupEngine($engine);

    $spec = $request->getStr('range');
    list($range_s, $range_e, $mask) =
      DifferentialChangesetParser::parseRangeSpecification($spec);
    $output = $parser->render($range_s, $range_e, $mask);

    if ($request->isAjax()) {
      return id(new PhabricatorChangesetResponse())
        ->setRenderedChangeset($output);
    }

    require_celerity_resource('differential-changeset-view-css');
    require_celerity_resource('syntax-highlighting-css');
    require_celerity_resource('phriction-document-css');

    Javelin::initBehavior('differential-show-more', array(
      'uri'         => '/phriction/diff/'.$document->getID().'/',
      'whitespace'  => $whitespace_mode,
    ));

    $slug = $document->getSlug();

    $revert_l = $this->renderRevertButton($content_l, $current);
    $revert_r = $this->renderRevertButton($content_r, $current);

    $crumbs = $this->buildApplicationCrumbs();
    $crumb_views = $this->renderBreadcrumbs($slug);
    foreach ($crumb_views as $view) {
      $crumbs->addCrumb($view);
    }


    $title = "Version $l vs $r";

    $header = id(new PhabricatorHeaderView())
      ->setHeader($title);

    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName($title));


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
          ),
          pht("\xC2\xAB Previous Change"));
      } else {
        $link_l = pht('Original Change');
      }

      $link_r = null;
      if ($nav_r) {
        $link_r = phutil_tag(
          'a',
          array(
            'href' => $uri->alter('l', $l + 1)->alter('r', $r + 1),
          ),
          pht("Next Change \xC2\xBB"));
      } else {
        $link_r = pht('Most Recent Change');
      }

      $navigation_table = hsprintf(
        '<table class="phriction-history-nav-table">
          <tr>
            <td class="nav-prev">%s</td>
            <td class="nav-next">%s</td>
          </tr>
        </table>',
        $link_l,
        $link_r);
    }



    $output = hsprintf(
      '<br><div class="phriction-document-history-diff">'.
        '%s<br /><br />%s'.
        '<table class="phriction-revert-table">'.
          '<tr><td>%s</td><td>%s</td>'.
        '</table>'.
        '%s'.
      '</div>',
      $comparison_table->render(),
      $navigation_table,
      $revert_l,
      $revert_r,
      $output);


    return $this->buildApplicationPage(
      array(
        $crumbs,
        $header,
        $output,
      ),
      array(
        'title'     => pht('Document History'),
        'device'    => true,
      ));

  }

  private function renderRevertButton(
    PhrictionContent $content,
    PhrictionContent $current) {

    $document_id = $content->getDocumentID();
    $version = $content->getVersion();

    if ($content->getChangeType() == PhrictionChangeType::CHANGE_DELETE) {
      // Don't show an edit/revert button for changes which deleted the content
      // since it's silly.
      return null;
    }

    if ($content->getID() == $current->getID()) {
      return phutil_tag(
        'a',
        array(
          'href'  => '/phriction/edit/'.$document_id.'/',
          'class' => 'button',
        ),
        pht('Edit Current Version'));
    }


    return phutil_tag(
      'a',
      array(
        'href'  => '/phriction/edit/'.$document_id.'/?revert='.$version,
        'class' => 'button',
      ),
      pht('Revert to Version %s...', $version));
  }

  private function renderComparisonTable(array $content) {
    assert_instances_of($content, 'PhrictionContent');

    $user = $this->getRequest()->getUser();

    $phids = mpull($content, 'getAuthorPHID');
    $handles = $this->loadViewerHandles($phids);

    $rows = array();
    foreach ($content as $c) {
      $rows[] = array(
        phabricator_date($c->getDateCreated(), $user),
        phabricator_time($c->getDateCreated(), $user),
        'Version '.$c->getVersion(),
        $handles[$c->getAuthorPHID()]->renderLink(),
        $c->getDescription(),
      );
    }


    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        pht('Date'),
        pht('Time'),
        pht('Version'),
        pht('Author'),
        pht('Description'),
      ));
    $table->setColumnClasses(
      array(
        '',
        'right',
        'pri',
        '',
        'wide',
      ));

    return $table;
  }

}
