<?php

/**
 * group paste
 */
final class PhabricatorPasteViewController extends PhabricatorPasteController {

  private $id;
  private $highlightMap;

  public function shouldAllowPublic() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
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

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $paste = id(new PhabricatorPasteQuery())
      ->setViewer($user)
      ->withIDs(array($this->id))
      ->needContent(true)
      ->executeOne();
    if (!$paste) {
      return new Aphront404Response();
    }

    $file = id(new PhabricatorFile())->loadOneWhere(
      'phid = %s',
      $paste->getFilePHID());
    if (!$file) {
      return new Aphront400Response();
    }

    $forks = id(new PhabricatorPasteQuery())
      ->setViewer($user)
      ->withParentPHIDs(array($paste->getPHID()))
      ->execute();
    $fork_phids = mpull($forks, 'getPHID');

    $this->loadHandles(
      array_merge(
        array(
          $paste->getAuthorPHID(),
          $paste->getParentPHID(),
        ),
        $fork_phids));

    $header = $this->buildHeaderView($paste);
    $actions = $this->buildActionView($user, $paste, $file);
    $properties = $this->buildPropertyView($paste, $fork_phids);
    $source_code = $this->buildSourceCodeView(
      $paste,
      null,
      $this->highlightMap);

    $crumbs = $this->buildApplicationCrumbs($this->buildSideNavView())
      ->setActionList($actions)
      ->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName('P'.$paste->getID())
          ->setHref('/P'.$paste->getID()));

    $xactions = id(new PhabricatorPasteTransactionQuery())
      ->setViewer($request->getUser())
      ->withObjectPHIDs(array($paste->getPHID()))
      ->execute();

    $engine = id(new PhabricatorMarkupEngine())
      ->setViewer($user);
    foreach ($xactions as $xaction) {
      if ($xaction->getComment()) {
        $engine->addObject(
          $xaction->getComment(),
          PhabricatorApplicationTransactionComment::MARKUP_FIELD_COMMENT);
      }
    }
    $engine->process();

    $timeline = id(new PhabricatorApplicationTransactionView())
      ->setUser($user)
      ->setObjectPHID($paste->getPHID())
      ->setTransactions($xactions)
      ->setMarkupEngine($engine);

    $is_serious = PhabricatorEnv::getEnvConfig('phabricator.serious-business');

    $add_comment_header = id(new PhabricatorHeaderView())
      ->setHeader(
        $is_serious
          ? pht('Add Comment')
          : pht('Debate Paste Accuracy'));

    $submit_button_name = $is_serious
      ? pht('Add Comment')
      : pht('Pity the Fool');

    $draft = PhabricatorDraft::newFromUserAndKey($user, $paste->getPHID());

    $add_comment_form = id(new PhabricatorApplicationTransactionCommentView())
      ->setUser($user)
      ->setObjectPHID($paste->getPHID())
      ->setDraft($draft)
      ->setAction($this->getApplicationURI('/comment/'.$paste->getID().'/'))
      ->setSubmitButtonName($submit_button_name);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $header,
        $actions,
        $properties,
        $source_code,
        $timeline,
        $add_comment_header,
        $add_comment_form
      ),
      array(
        'title' => $paste->getFullName(),
        'device' => true,
        'pageObjects' => array($paste->getPHID()),
      ));
  }

  private function buildHeaderView(PhabricatorPaste $paste) {
    return id(new PhabricatorHeaderView())
      ->setHeader($paste->getTitle());
  }

  private function buildActionView(
    PhabricatorUser $user,
    PhabricatorPaste $paste,
    PhabricatorFile $file) {

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $user,
      $paste,
      PhabricatorPolicyCapability::CAN_EDIT);

    $can_fork = $user->isLoggedIn();
    $fork_uri = $this->getApplicationURI('/create/?parent='.$paste->getID());

    return id(new PhabricatorActionListView())
      ->setUser($user)
      ->setObject($paste)
      ->setObjectURI($this->getRequest()->getRequestURI())
      ->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Fork This Paste'))
          ->setIcon('fork')
          ->setDisabled(!$can_fork)
          ->setWorkflow(!$can_fork)
          ->setHref($fork_uri))
      ->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('View Raw File'))
          ->setIcon('file')
          ->setHref($file->getBestURI()))
      ->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Edit Paste'))
          ->setIcon('edit')
          ->setDisabled(!$can_edit)
          ->setWorkflow(!$can_edit)
          ->setHref($this->getApplicationURI('/edit/'.$paste->getID().'/')));
  }

  private function buildPropertyView(
    PhabricatorPaste $paste,
    array $child_phids) {

    $user = $this->getRequest()->getUser();
    $properties = id(new PhabricatorPropertyListView())
      ->setUser($user)
      ->setObject($paste);

    $properties->addProperty(
      pht('Author'),
      $this->getHandle($paste->getAuthorPHID())->renderLink());

    $properties->addProperty(
      pht('Created'),
      phabricator_datetime($paste->getDateCreated(), $user));

    if ($paste->getParentPHID()) {
      $properties->addProperty(
        pht('Forked From'),
        $this->getHandle($paste->getParentPHID())->renderLink());
    }

    if ($child_phids) {
      $properties->addProperty(
        pht('Forks'),
        $this->renderHandlesForPHIDs($child_phids));
    }

    $descriptions = PhabricatorPolicyQuery::renderPolicyDescriptions(
      $user,
      $paste);

    $properties->addProperty(
      pht('Visible To'),
      $descriptions[PhabricatorPolicyCapability::CAN_VIEW]);

    return $properties;
  }

}
