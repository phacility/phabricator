<?php

/**
 * group paste
 */
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
    $properties = $this->buildPropertyView($paste, $fork_phids, $actions);

    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    $source_code = $this->buildSourceCodeView($paste, $this->highlightMap);

    require_celerity_resource('paste-css');
    $source_code = phutil_tag(
      'div',
      array(
        'class' => 'container-of-paste',
      ),
      $source_code);

    $crumbs = $this->buildApplicationCrumbs($this->buildSideNavView())
      ->addTextCrumb('P'.$paste->getID(), '/P'.$paste->getID());

    $timeline = $this->buildTransactionTimeline(
      $paste,
      new PhabricatorPasteTransactionQuery());

    $is_serious = PhabricatorEnv::getEnvConfig('phabricator.serious-business');

    $add_comment_header = $is_serious
      ? pht('Add Comment')
      : pht('Eat Paste');

    $draft = PhabricatorDraft::newFromUserAndKey($viewer, $paste->getPHID());

    $add_comment_form = id(new PhabricatorApplicationTransactionCommentView())
      ->setUser($viewer)
      ->setObjectPHID($paste->getPHID())
      ->setDraft($draft)
      ->setHeaderText($add_comment_header)
      ->setAction($this->getApplicationURI('/comment/'.$paste->getID().'/'))
      ->setSubmitButtonName(pht('Add Comment'));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $object_box,
        $source_code,
        $timeline,
        $add_comment_form,
      ),
      array(
        'title' => $paste->getFullName(),
        'pageObjects' => array($paste->getPHID()),
      ));
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
      ->setPolicyObject($paste);

    return $header;
  }

  private function buildActionView(
    PhabricatorUser $viewer,
    PhabricatorPaste $paste) {

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $paste,
      PhabricatorPolicyCapability::CAN_EDIT);

    $can_fork = $viewer->isLoggedIn();
    $id = $paste->getID();
    $fork_uri = $this->getApplicationURI('/create/?parent='.$id);

    return id(new PhabricatorActionListView())
      ->setUser($viewer)
      ->setObject($paste)
      ->setObjectURI($this->getRequest()->getRequestURI())
      ->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Edit Paste'))
          ->setIcon('fa-pencil')
          ->setDisabled(!$can_edit)
          ->setWorkflow(!$can_edit)
          ->setHref($this->getApplicationURI("edit/{$id}/")))
      ->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Fork This Paste'))
          ->setIcon('fa-code-fork')
          ->setDisabled(!$can_fork)
          ->setWorkflow(!$can_fork)
          ->setHref($fork_uri))
      ->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('View Raw File'))
          ->setIcon('fa-file-text-o')
          ->setHref($this->getApplicationURI("raw/{$id}/")));
  }

  private function buildPropertyView(
    PhabricatorPaste $paste,
    array $child_phids,
    PhabricatorActionListView $actions) {
    $viewer = $this->getViewer();

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($paste)
      ->setActionList($actions);

    $properties->addProperty(
      pht('Author'),
      $viewer->renderHandle($paste->getAuthorPHID()));

    $properties->addProperty(
      pht('Created'),
      phabricator_datetime($paste->getDateCreated(), $viewer));

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
