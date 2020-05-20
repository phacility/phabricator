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

    $recommendation_view = $this->newDocumentRecommendationView($paste);

    $paste_view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setSubheader($subheader)
      ->setMainColumn(
        array(
          $recommendation_view,
          $source_code,
          $timeline,
          $comment_view,
        ))
      ->setCurtain($curtain);

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

  private function newDocumentRecommendationView(PhabricatorPaste $paste) {
    $viewer = $this->getViewer();

    // See PHI1703. If a viewer is looking at a document in Paste which has
    // a good rendering via a DocumentEngine, suggest they view the content
    // in Files instead so they can see it rendered.

    $ref = id(new PhabricatorDocumentRef())
      ->setName($paste->getTitle())
      ->setData($paste->getRawContent());

    $engines = PhabricatorDocumentEngine::getEnginesForRef($viewer, $ref);
    if (!$engines) {
      return null;
    }

    $engine = head($engines);
    if (!$engine->shouldSuggestEngine($ref)) {
      return null;
    }

    $file = id(new PhabricatorFileQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($paste->getFilePHID()))
      ->executeOne();
    if (!$file) {
      return null;
    }

    $file_ref = id(new PhabricatorDocumentRef())
      ->setFile($file);

    $view_uri = id(new PhabricatorFileDocumentRenderingEngine())
      ->getRefViewURI($file_ref, $engine);

    $view_as_label = $engine->getViewAsLabel($file_ref);

    $view_as_hint = pht(
      'This content can be rendered as a document in Files.');

    return id(new PHUIInfoView())
      ->setSeverity(PHUIInfoView::SEVERITY_NOTICE)
      ->addButton(
        id(new PHUIButtonView())
          ->setTag('a')
          ->setText($view_as_label)
          ->setHref($view_uri)
          ->setColor('grey'))
      ->setErrors(
        array(
          $view_as_hint,
        ));
  }

}
