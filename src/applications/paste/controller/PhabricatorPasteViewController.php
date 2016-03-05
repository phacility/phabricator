<?php

final class PhabricatorPasteViewController extends PhabricatorPasteController {

  private $highlightMap;

  public function shouldAllowPublic() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $raw_lines = idx($data, 'lines');
    $map = array();
    if ($raw_lines) {
      $lines = explode('-', $raw_lines);
      $first = idx($lines, 0, 0);
      $last = idx($lines, 1);
      if ($last) {
        $min = min($first, $last);
        $max = max($first, $last);
        $map = array_fuse(range($min, $max));
      } else {
        $map[$first] = $first;
      }
    }
    $this->highlightMap = $map;
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

    $forks = id(new PhabricatorPasteQuery())
      ->setViewer($viewer)
      ->withParentPHIDs(array($paste->getPHID()))
      ->execute();
    $fork_phids = mpull($forks, 'getPHID');

    $header = $this->buildHeaderView($paste);
    $actions = $this->buildActionView($viewer, $paste);
    $properties = $this->buildPropertyView($paste, $fork_phids);
    $subheader = $this->buildSubheaderView($paste);
    $source_code = $this->buildSourceCodeView($paste, $this->highlightMap);

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
      ->setPropertyList($properties)
      ->setActionList($actions)
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

  private function buildActionView(
    PhabricatorUser $viewer,
    PhabricatorPaste $paste) {

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $paste,
      PhabricatorPolicyCapability::CAN_EDIT);

    $id = $paste->getID();

    $action_list = id(new PhabricatorActionListView())
      ->setUser($viewer)
      ->setObject($paste);

    $action_list->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Edit Paste'))
          ->setIcon('fa-pencil')
          ->setDisabled(!$can_edit)
          ->setHref($this->getApplicationURI("edit/{$id}/")));

    if ($paste->isArchived()) {
      $action_list->addAction(
        id(new PhabricatorActionView())
            ->setName(pht('Activate Paste'))
            ->setIcon('fa-check')
            ->setDisabled(!$can_edit)
            ->setWorkflow($can_edit)
            ->setHref($this->getApplicationURI("archive/{$id}/")));
    } else {
      $action_list->addAction(
        id(new PhabricatorActionView())
            ->setName(pht('Archive Paste'))
            ->setIcon('fa-ban')
            ->setDisabled(!$can_edit)
            ->setWorkflow($can_edit)
            ->setHref($this->getApplicationURI("archive/{$id}/")));
    }

    $action_list->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('View Raw File'))
          ->setIcon('fa-file-text-o')
          ->setHref($this->getApplicationURI("raw/{$id}/")));

    return $action_list;
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

  private function buildPropertyView(
    PhabricatorPaste $paste,
    array $child_phids) {
    $viewer = $this->getViewer();

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($paste);

    if ($paste->getParentPHID()) {
      $properties->addProperty(
        pht('Forked From'),
        $viewer->renderHandle($paste->getParentPHID()));
    }

    if ($child_phids) {
      $properties->addProperty(
        pht('Forks'),
        $viewer->renderHandleList($child_phids));
    }

    $descriptions = PhabricatorPolicyQuery::renderPolicyDescriptions(
      $viewer,
      $paste);

    return $properties;
  }

}
