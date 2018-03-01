<?php

final class PhabricatorPasteViewController extends PhabricatorPasteController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $paste = id(new PhabricatorPasteQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->needContent(true)
      ->needRawContent(true)
      ->executeOne();
    if (!$paste) {
      return new Aphront404Response();
    }

    $lines = $request->getURILineRange('lines', 1000);
    if ($lines) {
      $map = range($lines[0], $lines[1]);
    } else {
      $map = array();
    }

    $header = $this->buildHeaderView($paste);
    $curtain = $this->buildCurtain($paste);

    $subheader = $this->buildSubheaderView($paste);
    $source_code = $this->buildSourceCodeView($paste, $map);

    require_celerity_resource('paste-css');

    $monogram = $paste->getMonogram();
    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb($monogram)
      ->setBorder(true);

    $timeline = $this->buildTransactionTimeline(
      $paste,
      new PhabricatorPasteTransactionQuery());

    $comment_view = id(new PhabricatorPasteEditEngine())
      ->setViewer($viewer)
      ->buildEditEngineCommentView($paste);

    $timeline->setQuoteRef($monogram);
    $comment_view->setTransactionTimeline($timeline);

    $paste_view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setSubheader($subheader)
      ->setMainColumn(array(
          $source_code,
          $timeline,
          $comment_view,
        ))
      ->setCurtain($curtain)
      ->addClass('ponder-question-view');

    return $this->newPage()
      ->setTitle($paste->getFullName())
      ->setCrumbs($crumbs)
      ->setPageObjectPHIDs(
        array(
          $paste->getPHID(),
        ))
      ->appendChild($paste_view);
  }

  private function buildHeaderView(PhabricatorPaste $paste) {
    $title = (nonempty($paste->getTitle())) ?
      $paste->getTitle() : pht('(An Untitled Masterwork)');

    if ($paste->isArchived()) {
      $header_icon = 'fa-ban';
      $header_name = pht('Archived');
      $header_color = 'dark';
    } else {
      $header_icon = 'fa-check';
      $header_name = pht('Active');
      $header_color = 'bluegrey';
    }

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setUser($this->getRequest()->getUser())
      ->setStatus($header_icon, $header_color, $header_name)
      ->setPolicyObject($paste)
      ->setHeaderIcon('fa-clipboard');

    return $header;
  }

  private function buildCurtain(PhabricatorPaste $paste) {
    $viewer = $this->getViewer();
    $curtain = $this->newCurtainView($paste);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $paste,
      PhabricatorPolicyCapability::CAN_EDIT);

    $id = $paste->getID();
    $edit_uri = $this->getApplicationURI("edit/{$id}/");
    $archive_uri = $this->getApplicationURI("archive/{$id}/");
    $raw_uri = $this->getApplicationURI("raw/{$id}/");

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Paste'))
        ->setIcon('fa-pencil')
        ->setDisabled(!$can_edit)
        ->setHref($edit_uri));

    if ($paste->isArchived()) {
      $curtain->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Activate Paste'))
          ->setIcon('fa-check')
          ->setDisabled(!$can_edit)
          ->setWorkflow($can_edit)
          ->setHref($archive_uri));
    } else {
      $curtain->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Archive Paste'))
          ->setIcon('fa-ban')
          ->setDisabled(!$can_edit)
          ->setWorkflow($can_edit)
          ->setHref($archive_uri));
    }

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('View Raw File'))
        ->setIcon('fa-file-text-o')
        ->setHref($raw_uri));

    return $curtain;
  }


  private function buildSubheaderView(
    PhabricatorPaste $paste) {
    $viewer = $this->getViewer();

    $author = $viewer->renderHandle($paste->getAuthorPHID())->render();
    $date = phabricator_datetime($paste->getDateCreated(), $viewer);
    $author = phutil_tag('strong', array(), $author);

    $author_info = id(new PhabricatorPeopleQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($paste->getAuthorPHID()))
      ->needProfileImage(true)
      ->executeOne();

    $image_uri = $author_info->getProfileImageURI();
    $image_href = '/p/'.$author_info->getUsername();

    $content = pht('Authored by %s on %s.', $author, $date);

    return id(new PHUIHeadThingView())
      ->setImage($image_uri)
      ->setImageHref($image_href)
      ->setContent($content);
  }

}
