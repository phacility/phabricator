<?php

final class DifferentialRevisionInlinesController
  extends DifferentialController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $id = $request->getURIData('id');

    $revision = id(new DifferentialRevisionQuery())
      ->withIDs(array($id))
      ->setViewer($viewer)
      ->needDiffIDs(true)
      ->executeOne();
    if (!$revision) {
      return new Aphront404Response();
    }

    $revision_monogram = $revision->getMonogram();
    $revision_uri = $revision->getURI();
    $revision_title = $revision->getTitle();

    $inlines = id(new DifferentialDiffInlineCommentQuery())
      ->setViewer($viewer)
      ->withRevisionPHIDs(array($revision->getPHID()))
      ->withPublishedComments(true)
      ->execute();
    $inlines = mpull($inlines, 'newInlineCommentObject');

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($revision_monogram, $revision_uri);
    $crumbs->addTextCrumb(pht('Inline Comments'));
    $crumbs->setBorder(true);

    $content = $this->renderInlineTable($revision, $inlines);
    $header = $this->buildHeader($revision);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter($content);

    return $this->newPage()
      ->setTitle(
        array(
          "{$revision_monogram} {$revision_title}",
          pht('Inlines'),
        ))
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

  private function buildHeader(DifferentialRevision $revision) {
    $viewer = $this->getViewer();

    $button = id(new PHUIButtonView())
      ->setTag('a')
      ->setIcon('fa-chevron-left')
      ->setHref($revision->getURI())
      ->setText(pht('Back to Revision'));

    return id(new PHUIHeaderView())
      ->setHeader($revision->getTitle())
      ->setUser($viewer)
      ->setHeaderIcon('fa-cog')
      ->addActionLink($button);
  }

  private function renderInlineTable(
    DifferentialRevision $revision,
    array $inlines) {

    $viewer = $this->getViewer();
    $inlines = id(new PHUIDiffInlineThreader())
      ->reorderAndThreadCommments($inlines);

    $handle_phids = array();
    $changeset_ids = array();
    foreach ($inlines as $inline) {
      $handle_phids[] = $inline->getAuthorPHID();
      $changeset_ids[] = $inline->getChangesetID();
    }
    $handles = $viewer->loadHandles($handle_phids);
    $handles = iterator_to_array($handles);

    if ($changeset_ids) {
      $changesets = id(new DifferentialChangesetQuery())
        ->setViewer($viewer)
        ->withIDs($changeset_ids)
        ->execute();
      $changesets = mpull($changesets, null, 'getID');
    } else {
      $changesets = array();
    }

    $current_changeset = head($revision->getDiffIDs());

    $rows = array();
    foreach ($inlines as $inline) {
      $status_icons = array();

      $c_id = $inline->getChangesetID();
      $d_id = $changesets[$c_id]->getDiffID();

      if ($d_id == $current_changeset) {
        $diff_id = phutil_tag('strong', array(), pht('Current'));
      } else {
        $diff_id = pht('Diff %d', $d_id);
      }

      $reviewer = $handles[$inline->getAuthorPHID()]->renderLink();
      $now = PhabricatorTime::getNow();
      $then = $inline->getDateModified();
      $datetime = phutil_format_relative_time($now - $then);

      $comment_href = $revision->getURI().'#inline-'.$inline->getID();
      $comment = phutil_tag(
        'a',
        array(
          'href' => $comment_href,
        ),
        $inline->getContent());

      $state = $inline->getFixedState();
      if ($state == PhabricatorInlineComment::STATE_DONE) {
        $status_icons[] = id(new PHUIIconView())
          ->setIcon('fa-check green')
          ->addClass('mmr');
      } else if ($inline->getReplyToCommentPHID() &&
        $inline->getAuthorPHID() == $revision->getAuthorPHID()) {
        $status_icons[] = id(new PHUIIconView())
          ->setIcon('fa-commenting-o blue')
          ->addClass('mmr');
      } else {
        $status_icons[] = id(new PHUIIconView())
          ->setIcon('fa-circle-o grey')
          ->addClass('mmr');
      }


      if ($inline->getReplyToCommentPHID()) {
        $reply_icon = id(new PHUIIconView())
          ->setIcon('fa-reply mmr darkgrey');
        $comment = array($reply_icon, $comment);
      }

      $rows[] = array(
        $diff_id,
        $status_icons,
        $reviewer,
        AphrontTableView::renderSingleDisplayLine($comment),
        $datetime,
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        pht('Diff'),
        pht('Status'),
        pht('Reviewer'),
        pht('Comment'),
        pht('Created'),
      ));
    $table->setColumnClasses(
      array(
        '',
        '',
        '',
        'wide',
        'right',
      ));
    $table->setColumnVisibility(
      array(
        true,
        true,
        true,
        true,
        true,
      ));

    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Inline Comments'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setTable($table);
  }

}
