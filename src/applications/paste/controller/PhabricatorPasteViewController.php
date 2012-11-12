<?php

final class PhabricatorPasteViewController extends PhabricatorPasteController {

  public function shouldAllowPublic() {
    return true;
  }

  private $id;
  private $handles;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $paste = id(new PhabricatorPasteQuery())
      ->setViewer($user)
      ->withIDs(array($this->id))
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
    $source_code = $this->buildSourceCodeView($paste, $file);

    $nav = $this->buildSideNavView($paste);
    $nav->selectFilter('paste');

    $nav->appendChild(
      array(
        $header,
        $actions,
        $properties,
        $source_code,
      ));

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => $paste->getFullName(),
        'device' => true,
      ));
  }

  private function buildHeaderView(PhabricatorPaste $paste) {
    return id(new PhabricatorHeaderView())
      ->setObjectName('P'.$paste->getID())
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

    return id(new PhabricatorActionListView())
      ->setUser($user)
      ->setObject($paste)
      ->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Fork This Paste'))
          ->setIcon('fork')
          ->setDisabled(!$can_fork)
          ->setWorkflow(!$can_fork)
          ->setHref($this->getApplicationURI('?parent='.$paste->getID())))
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
    $properties = new PhabricatorPropertyListView();

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

  private function buildSourceCodeView(
    PhabricatorPaste $paste,
    PhabricatorFile $file) {

    $language = $paste->getLanguage();
    $source = $file->loadFileData();

    if (empty($language)) {
      $source = PhabricatorSyntaxHighlighter::highlightWithFilename(
        $paste->getTitle(),
        $source);
    } else {
      $source = PhabricatorSyntaxHighlighter::highlightWithLanguage(
        $language,
        $source);
    }

    $lines = explode("\n", $source);

    return id(new PhabricatorSourceCodeView())
      ->setLines($lines);
  }

}
