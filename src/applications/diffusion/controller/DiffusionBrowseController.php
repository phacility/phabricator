<?php

abstract class DiffusionBrowseController extends DiffusionController {

  public function shouldAllowPublic() {
    return true;
  }

  protected function renderSearchForm($collapsed) {
    $drequest = $this->getDiffusionRequest();

    $forms = array();
    $form = id(new AphrontFormView())
      ->setUser($this->getRequest()->getUser())
      ->setMethod('GET');

    switch ($drequest->getRepository()->getVersionControlSystem()) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        $forms[] = id(clone $form)
          ->appendChild(pht('Search is not available in Subversion.'));
        break;
      default:
        $forms[] = id(clone $form)
          ->appendChild(
            id(new AphrontFormTextWithSubmitControl())
              ->setLabel(pht('File Name'))
              ->setSubmitLabel(pht('Search File Names'))
              ->setName('find')
              ->setValue($this->getRequest()->getStr('find')));
        $forms[] = id(clone $form)
          ->appendChild(
            id(new AphrontFormTextWithSubmitControl())
              ->setLabel(pht('Pattern'))
              ->setSubmitLabel(pht('Grep File Content'))
              ->setName('grep')
              ->setValue($this->getRequest()->getStr('grep')));
        break;
    }

    $filter = new AphrontListFilterView();
    $filter->appendChild($forms);

    if ($collapsed) {
      $filter->setCollapsed(
        pht('Show Search'),
        pht('Hide Search'),
        pht('Search for file names or content in this directory.'),
        '#');
    }

    $filter = id(new PHUIBoxView())
      ->addClass('mlt mlb')
      ->appendChild($filter);

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
        ->setIcon('fa-list'));

    $behind_head = $drequest->getSymbolicCommit();
    $head_uri = $drequest->generateURI(
      array(
        'commit' => '',
        'action' => 'browse',
      ));
    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Jump to HEAD'))
        ->setHref($head_uri)
        ->setIcon('fa-home')
        ->setDisabled(!$behind_head));

    return $view;
  }

  protected function buildPropertyView(
    DiffusionRequest $drequest,
    PhabricatorActionListView $actions) {

    $viewer = $this->getViewer();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setActionList($actions);

    $stable_commit = $drequest->getStableCommit();
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

    if ($drequest->getSymbolicType() == 'tag') {
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
        $view->addSectionHeader(
          pht('Tag Content'), 'fa-tag');
        $view->addTextContent($this->markupText($tag->getMessage()));
      }
    }

    $repository = $drequest->getRepository();

    $owners = 'PhabricatorOwnersApplication';
    if (PhabricatorApplication::isClassInstalled($owners)) {
      $package_query = id(new PhabricatorOwnersPackageQuery())
        ->setViewer($viewer)
        ->withStatuses(array(PhabricatorOwnersPackage::STATUS_ACTIVE))
        ->withControl(
          $repository->getPHID(),
          array(
            $drequest->getPath(),
          ));

      $package_query->execute();

      $packages = $package_query->getControllingPackagesForPath(
        $repository->getPHID(),
        $drequest->getPath());

      if ($packages) {
        $ownership = id(new PHUIStatusListView())
          ->setUser($viewer);

        foreach ($packages as $package) {
          $icon = 'fa-list-alt';
          $color = 'grey';

          $item = id(new PHUIStatusItemView())
            ->setIcon($icon, $color)
            ->setTarget($viewer->renderHandle($package->getPHID()));

          $ownership->addItem($item);
        }
      } else {
        $ownership = phutil_tag('em', array(), pht('None'));
      }

      $view->addProperty(pht('Packages'), $ownership);
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

    $recent = (PhabricatorTime::getNow() - phutil_units('30 days in seconds'));

    $revisions = id(new DifferentialRevisionQuery())
      ->setViewer($user)
      ->withPath($repository->getID(), $path_id)
      ->withStatus(DifferentialRevisionQuery::STATUS_OPEN)
      ->withUpdatedEpochBetween($recent, null)
      ->setOrder(DifferentialRevisionQuery::ORDER_MODIFIED)
      ->setLimit(10)
      ->needRelationships(true)
      ->needFlags(true)
      ->needDrafts(true)
      ->execute();

    if (!$revisions) {
      return null;
    }

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Open Revisions'))
      ->setSubheader(
        pht('Recently updated open revisions affecting this file.'));

    $view = id(new DifferentialRevisionListView())
      ->setHeader($header)
      ->setRevisions($revisions)
      ->setUser($user);

    $phids = $view->getRequiredHandlePHIDs();
    $handles = $this->loadViewerHandles($phids);
    $view->setHandles($handles);

    return $view;
  }

}
