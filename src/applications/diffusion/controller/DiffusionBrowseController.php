<?php

abstract class DiffusionBrowseController extends DiffusionController {

  public function shouldAllowPublic() {
    return true;
  }

  protected function renderSearchForm($collapsed) {
    $drequest = $this->getDiffusionRequest();
    $form = id(new AphrontFormView())
      ->setUser($this->getRequest()->getUser())
      ->setMethod('GET');

    switch ($drequest->getRepository()->getVersionControlSystem()) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        $form->appendChild(pht('Search is not available in Subversion.'));
        break;

      default:
        $form
          ->appendChild(
            id(new AphrontFormTextControl())
              ->setLabel(pht('Search Here'))
              ->setName('grep')
              ->setValue($this->getRequest()->getStr('grep'))
              ->setCaption(pht('Enter a regular expression.')))
          ->appendChild(
            id(new AphrontFormSubmitControl())
              ->setValue(pht('Search File Content')));
        break;
    }

    $filter = new AphrontListFilterView();
    $filter->appendChild($form);

    if ($collapsed) {
      $filter->setCollapsed(
        pht('Show Search'),
        pht('Hide Search'),
        pht('Search for file content in this directory.'),
        '#');
    }

    return $filter;
  }

  protected function markupText($text) {
    $engine = PhabricatorMarkupEngine::newDiffusionMarkupEngine();
    $engine->setConfig('viewer', $this->getRequest()->getUser());
    $text = $engine->markupText($text);

    $text = phutil_tag(
      'div',
      array(
        'class' => 'phabricator-remarkup',
      ),
      $text);

    return $text;
  }

  protected function buildHeaderView(DiffusionRequest $drequest) {
    $viewer = $this->getRequest()->getUser();

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setHeader($this->renderPathLinks($drequest, $mode = 'browse'))
      ->setPolicyObject($drequest->getRepository());

    return $header;
  }

  protected function buildActionView(DiffusionRequest $drequest) {
    $viewer = $this->getRequest()->getUser();

    $view = id(new PhabricatorActionListView())
      ->setUser($viewer);

    $history_uri = $drequest->generateURI(
      array(
        'action' => 'history',
      ));

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('View History'))
        ->setHref($history_uri)
        ->setIcon('history'));

    $behind_head = $drequest->getRawCommit();
    $head_uri = $drequest->generateURI(
      array(
        'commit' => '',
        'action' => 'browse',
      ));
    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Jump to HEAD'))
        ->setHref($head_uri)
        ->setIcon('home')
        ->setDisabled(!$behind_head));

    // TODO: Ideally, this should live in Owners and be event-triggered, but
    // there's no reasonable object for it to react to right now.

    $owners = 'PhabricatorApplicationOwners';
    if (PhabricatorApplication::isClassInstalled($owners)) {
      $owners_uri = id(new PhutilURI('/owners/view/search/'))
        ->setQueryParams(
          array(
            'repository' => $drequest->getCallsign(),
            'path' => '/'.$drequest->getPath(),
          ));

      $view->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Find Owners'))
          ->setHref((string)$owners_uri)
          ->setIcon('preview'));
    }

    return $view;
  }

  protected function buildPropertyView(
    DiffusionRequest $drequest,
    PhabricatorActionListView $actions) {

    $viewer = $this->getRequest()->getUser();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setActionList($actions);

    $stable_commit = $drequest->getStableCommitName();
    $callsign = $drequest->getRepository()->getCallsign();

    $view->addProperty(
      pht('Commit'),
      phutil_tag(
        'a',
        array(
          'href' => $drequest->generateURI(
            array(
              'action' => 'commit',
              'commit' => $stable_commit,
            )),
        ),
        $drequest->getRepository()->formatCommitName($stable_commit)));

    if ($drequest->getCommitType() == 'tag') {
      $symbolic = $drequest->getSymbolicCommit();
      $view->addProperty(pht('Tag'), $symbolic);

      $tags = $this->callConduitWithDiffusionRequest(
        'diffusion.tagsquery',
        array(
          'names' => array($symbolic),
          'needMessages' => true,
        ));
      $tags = DiffusionRepositoryTag::newFromConduit($tags);

      $tags = mpull($tags, null, 'getName');
      $tag = idx($tags, $symbolic);

      if ($tag && strlen($tag->getMessage())) {
        $view->addSectionHeader(pht('Tag Content'));
        $view->addTextContent($this->markupText($tag->getMessage()));
      }
    }

    return $view;
  }

  protected function buildOpenRevisions() {
    $user = $this->getRequest()->getUser();

    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();
    $path = $drequest->getPath();

    $path_map = id(new DiffusionPathIDQuery(array($path)))->loadPathIDs();
    $path_id = idx($path_map, $path);
    if (!$path_id) {
      return null;
    }

    $revisions = id(new DifferentialRevisionQuery())
      ->setViewer($user)
      ->withPath($repository->getID(), $path_id)
      ->withStatus(DifferentialRevisionQuery::STATUS_OPEN)
      ->setOrder(DifferentialRevisionQuery::ORDER_PATH_MODIFIED)
      ->setLimit(10)
      ->needRelationships(true)
      ->execute();

    if (!$revisions) {
      return null;
    }

    $view = id(new DifferentialRevisionListView())
      ->setRevisions($revisions)
      ->setFields(DifferentialRevisionListView::getDefaultFields($user))
      ->setUser($user)
      ->loadAssets();

    $phids = $view->getRequiredHandlePHIDs();
    $handles = $this->loadViewerHandles($phids);
    $view->setHandles($handles);

    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Pending Differential Revisions'))
      ->appendChild($view);

  }

}
