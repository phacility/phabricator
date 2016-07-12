<?php

final class DiffusionChangeController extends DiffusionController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $response = $this->loadDiffusionContext();
    if ($response) {
      return $response;
    }

    $viewer = $this->getViewer();
    $drequest = $this->getDiffusionRequest();

    $data = $this->callConduitWithDiffusionRequest(
      'diffusion.diffquery',
      array(
        'commit' => $drequest->getCommit(),
        'path' => $drequest->getPath(),
      ));

    $drequest->updateSymbolicCommit($data['effectiveCommit']);

    $raw_changes = ArcanistDiffChange::newFromConduit($data['changes']);
    $diff = DifferentialDiff::newEphemeralFromRawChanges(
      $raw_changes);
    $changesets = $diff->getChangesets();
    $changeset = reset($changesets);

    if (!$changeset) {
      // TODO: Refine this.
      return new Aphront404Response();
    }

    $repository = $drequest->getRepository();
    $changesets = array(
      0 => $changeset,
    );

    $changeset_header = $this->buildChangesetHeader($drequest);

    $changeset_view = new DifferentialChangesetListView();
    $changeset_view->setChangesets($changesets);
    $changeset_view->setBackground(PHUIObjectBoxView::BLUE_PROPERTY);
    $changeset_view->setVisibleChangesets($changesets);
    $changeset_view->setRenderingReferences(
      array(
        0 => $drequest->generateURI(array('action' => 'rendering-ref')),
      ));

    $raw_params = array(
      'action' => 'browse',
      'params' => array(
        'view' => 'raw',
      ),
    );

    $right_uri = $drequest->generateURI($raw_params);
    $raw_params['params']['before'] = $drequest->getStableCommit();
    $left_uri = $drequest->generateURI($raw_params);
    $changeset_view->setRawFileURIs($left_uri, $right_uri);

    $changeset_view->setRenderURI($repository->getPathURI('diff/'));

    $changeset_view->setWhitespace(
      DifferentialChangesetParser::WHITESPACE_SHOW_ALL);
    $changeset_view->setUser($viewer);
    $changeset_view->setHeader($changeset_header);

    // TODO: This is pretty awkward, unify the CSS between Diffusion and
    // Differential better.
    require_celerity_resource('differential-core-view-css');

    $crumbs = $this->buildCrumbs(
      array(
        'branch' => true,
        'path'   => true,
        'view'   => 'change',
      ));
    $crumbs->setBorder(true);

    $links = $this->renderPathLinks($drequest, $mode = 'browse');
    $header = $this->buildHeader($drequest, $links);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setMainColumn(array(
      ))
      ->setFooter(array(
        $changeset_view,
      ));

    return $this->newPage()
      ->setTitle(
        array(
          basename($drequest->getPath()),
          $repository->getDisplayName(),
        ))
      ->setCrumbs($crumbs)
      ->appendChild(
        array(
          $view,
        ));
  }

  private function buildHeader(
    DiffusionRequest $drequest,
    $links) {
    $viewer = $this->getViewer();

    $tag = $this->renderCommitHashTag($drequest);

    $header = id(new PHUIHeaderView())
      ->setHeader($links)
      ->setUser($viewer)
      ->setPolicyObject($drequest->getRepository())
      ->addTag($tag);

    return $header;
  }

  private function buildChangesetHeader(DiffusionRequest $drequest) {
    $viewer = $this->getViewer();

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Changes'));

    $history_uri = $drequest->generateURI(
      array(
        'action' => 'history',
      ));

    $header->addActionLink(
      id(new PHUIButtonView())
        ->setTag('a')
        ->setText(pht('View History'))
        ->setHref($history_uri)
        ->setIcon('fa-clock-o'));

    $browse_uri = $drequest->generateURI(
      array(
        'action' => 'browse',
      ));

    $header->addActionLink(
      id(new PHUIButtonView())
        ->setTag('a')
        ->setText(pht('Browse Content'))
        ->setHref($browse_uri)
        ->setIcon('fa-files-o'));

    return $header;
  }

  protected function buildPropertyView(
    DiffusionRequest $drequest,
    PhabricatorActionListView $actions) {

    $viewer = $this->getRequest()->getUser();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setActionList($actions);

    $stable_commit = $drequest->getStableCommit();

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

    return $view;
  }

}
