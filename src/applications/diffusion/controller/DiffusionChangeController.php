<?php

final class DiffusionChangeController extends DiffusionController {

  public function shouldAllowPublic() {
    return true;
  }

  protected function processDiffusionRequest(AphrontRequest $request) {
    $drequest = $this->diffusionRequest;
    $viewer = $request->getUser();

    $content = array();

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
    $callsign = $repository->getCallsign();
    $changesets = array(
      0 => $changeset,
    );

    $changeset_view = new DifferentialChangesetListView();
    $changeset_view->setTitle(pht('Change'));
    $changeset_view->setChangesets($changesets);
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

    $changeset_view->setRenderURI('/diffusion/'.$callsign.'/diff/');
    $changeset_view->setWhitespace(
      DifferentialChangesetParser::WHITESPACE_SHOW_ALL);
    $changeset_view->setUser($viewer);

    // TODO: This is pretty awkward, unify the CSS between Diffusion and
    // Differential better.
    require_celerity_resource('differential-core-view-css');
    $content[] = $changeset_view->render();

    $crumbs = $this->buildCrumbs(
      array(
        'branch' => true,
        'path'   => true,
        'view'   => 'change',
      ));

    $links = $this->renderPathLinks($drequest, $mode = 'browse');

    $header = id(new PHUIHeaderView())
      ->setHeader($links)
      ->setUser($viewer)
      ->setPolicyObject($drequest->getRepository());
    $actions = $this->buildActionView($drequest);
    $properties = $this->buildPropertyView($drequest, $actions);

    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $object_box,
        $content,
      ),
      array(
        'title' => pht('Change'),
      ));
  }

  private function buildActionView(DiffusionRequest $drequest) {
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
        ->setIcon('fa-clock-o'));

    $browse_uri = $drequest->generateURI(
      array(
        'action' => 'browse',
      ));

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Browse Content'))
        ->setHref($browse_uri)
        ->setIcon('fa-files-o'));

    return $view;
  }

  protected function buildPropertyView(
    DiffusionRequest $drequest,
    PhabricatorActionListView $actions) {

    $viewer = $this->getRequest()->getUser();

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

    return $view;
  }

}
