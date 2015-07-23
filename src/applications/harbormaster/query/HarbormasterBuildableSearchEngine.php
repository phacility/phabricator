<?php

final class HarbormasterBuildableSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Harbormaster Buildables');
  }

  public function getApplicationClassName() {
    return 'PhabricatorHarbormasterApplication';
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    $revisions = $this->readPHIDsFromRequest(
      $request,
      'revisions',
      array(
        DifferentialRevisionPHIDType::TYPECONST,
      ));

    $repositories = $this->readPHIDsFromRequest(
      $request,
      'repositories',
      array(
        PhabricatorRepositoryRepositoryPHIDType::TYPECONST,
      ));

    $container_phids = array_merge($revisions, $repositories);
    $saved->setParameter('containerPHIDs', $container_phids);

    $commits = $this->readPHIDsFromRequest(
      $request,
      'commits',
      array(
        PhabricatorRepositoryCommitPHIDType::TYPECONST,
      ));

    $diffs = $this->readListFromRequest($request, 'diffs');
    if ($diffs) {
      $diffs = id(new DifferentialDiffQuery())
        ->setViewer($this->requireViewer())
        ->withIDs($diffs)
        ->execute();
      $diffs = mpull($diffs, 'getPHID', 'getPHID');
    }

    $buildable_phids = array_merge($commits, $diffs);
    $saved->setParameter('buildablePHIDs', $buildable_phids);

    $saved->setParameter(
      'manual',
      $this->readBoolFromRequest($request, 'manual'));

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new HarbormasterBuildableQuery())
      ->needContainerHandles(true)
      ->needBuildableHandles(true);

    $container_phids = $saved->getParameter('containerPHIDs', array());
    if ($container_phids) {
      $query->withContainerPHIDs($container_phids);
    }

    $buildable_phids = $saved->getParameter('buildablePHIDs', array());

    if ($buildable_phids) {
      $query->withBuildablePHIDs($buildable_phids);
    }

    $manual = $saved->getParameter('manual');
    if ($manual !== null) {
      $query->withManualBuildables($manual);
    }

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved_query) {

    $container_phids = $saved_query->getParameter('containerPHIDs', array());
    $buildable_phids = $saved_query->getParameter('buildablePHIDs', array());

    $all_phids = array_merge($container_phids, $buildable_phids);

    $revision_names = array();
    $diff_names = array();
    $repository_names = array();
    $commit_names = array();

    if ($all_phids) {
      $objects = id(new PhabricatorObjectQuery())
        ->setViewer($this->requireViewer())
        ->withPHIDs($all_phids)
        ->execute();

      foreach ($all_phids as $phid) {
        $object = idx($objects, $phid);
        if (!$object) {
          continue;
        }

        if ($object instanceof DifferentialRevision) {
          $revision_names[] = 'D'.$object->getID();
        } else if ($object instanceof DifferentialDiff) {
          $diff_names[] = $object->getID();
        } else if ($object instanceof PhabricatorRepository) {
          $repository_names[] = 'r'.$object->getCallsign();
        } else if ($object instanceof PhabricatorRepositoryCommit) {
          $repository = $object->getRepository();
          $commit_names[] = $repository->formatCommitName(
            $object->getCommitIdentifier());
        }
      }
    }

    $form
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Differential Revisions'))
          ->setName('revisions')
          ->setValue(implode(', ', $revision_names)))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Differential Diffs'))
          ->setName('diffs')
          ->setValue(implode(', ', $diff_names)))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Repositories'))
          ->setName('repositories')
          ->setValue(implode(', ', $repository_names)))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Commits'))
          ->setName('commits')
          ->setValue(implode(', ', $commit_names)))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Origin'))
          ->setName('manual')
          ->setValue($this->getBoolFromQuery($saved_query, 'manual'))
          ->setOptions(
            array(
              '' => pht('(All Origins)'),
              'true' => pht('Manual Buildables'),
              'false' => pht('Automatic Buildables'),
            )));
  }

  protected function getURI($path) {
    return '/harbormaster/'.$path;
  }

  protected function getBuiltinQueryNames() {
    return array(
      'all' => pht('All Buildables'),
    );
  }

  public function buildSavedQueryFromBuiltin($query_key) {
    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'all':
        return $query;
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  protected function renderResultList(
    array $buildables,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($buildables, 'HarbormasterBuildable');

    $viewer = $this->requireViewer();

    $list = new PHUIObjectItemListView();
    foreach ($buildables as $buildable) {
      $id = $buildable->getID();

      $item = id(new PHUIObjectItemView())
        ->setHeader(pht('Buildable %d', $buildable->getID()));
      if ($buildable->getContainerHandle() !== null) {
        $item->addAttribute($buildable->getContainerHandle()->getName());
      }
      if ($buildable->getBuildableHandle() !== null) {
        $item->addAttribute($buildable->getBuildableHandle()->getFullName());
      }

      if ($id) {
        $item->setHref("/B{$id}");
      }

      if ($buildable->getIsManualBuildable()) {
        $item->addIcon('fa-wrench grey', pht('Manual'));
      }

      $item->setStatusIcon('fa-wrench '.
        HarbormasterBuildable::getBuildableStatusColor(
        $buildable->getBuildableStatus()));
      $item->addByline(HarbormasterBuildable::getBuildableStatusName(
        $buildable->getBuildableStatus()));

      $list->addItem($item);

    }

    $result = new PhabricatorApplicationSearchResultView();
    $result->setObjectList($list);
    $result->setNoDataString(pht('No buildables found.'));

    return $result;
  }

}
