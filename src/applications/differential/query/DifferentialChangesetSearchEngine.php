<?php

final class DifferentialChangesetSearchEngine
  extends PhabricatorApplicationSearchEngine {

  private $diff;

  public function setDiff(DifferentialDiff $diff) {
    $this->diff = $diff;
    return $this;
  }

  public function getDiff() {
    return $this->diff;
  }

  public function getResultTypeDescription() {
    return pht('Differential Changesets');
  }

  public function getApplicationClassName() {
    return 'PhabricatorDifferentialApplication';
  }

  public function canUseInPanelContext() {
    return false;
  }

  public function newQuery() {
    $query = id(new DifferentialChangesetQuery());

    if ($this->diff) {
      $query->withDiffs(array($this->diff));
    }

    return $query;
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    if ($map['diffPHIDs']) {
      $query->withDiffPHIDs($map['diffPHIDs']);
    }

    return $query;
  }

  protected function buildCustomSearchFields() {
    return array(
      id(new PhabricatorPHIDsSearchField())
        ->setLabel(pht('Diffs'))
        ->setKey('diffPHIDs')
        ->setAliases(array('diff', 'diffs', 'diffPHID'))
        ->setDescription(
          pht('Find changesets attached to a particular diff.')),
    );
  }

  protected function getURI($path) {
    $diff = $this->getDiff();
    if ($diff) {
      return '/differential/diff/'.$diff->getID().'/changesets/'.$path;
    }

    throw new PhutilMethodNotImplementedException();
  }

  protected function getBuiltinQueryNames() {
    $names = array();
    $names['all'] = pht('All Changesets');
    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {
    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    $viewer = $this->requireViewer();

    switch ($query_key) {
      case 'all':
        return $query->setParameter('order', 'oldest');
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  protected function renderResultList(
    array $changesets,
    PhabricatorSavedQuery $query,
    array $handles) {

    assert_instances_of($changesets, 'DifferentialChangeset');
    $viewer = $this->requireViewer();

    $rows = array();
    foreach ($changesets as $changeset) {
      $link = phutil_tag(
        'a',
        array(
          'href' => '/differential/changeset/?ref='.$changeset->getID(),
        ),
        $changeset->getDisplayFilename());

      $type = $changeset->getChangeType();

      $title = DifferentialChangeType::getFullNameForChangeType($type);

      $add_lines = $changeset->getAddLines();
      if (!$add_lines) {
        $add_lines = null;
      } else {
        $add_lines = '+'.$add_lines;
      }

      $rem_lines = $changeset->getDelLines();
      if (!$rem_lines) {
        $rem_lines = null;
      } else {
        $rem_lines = '-'.$rem_lines;
      }

      $rows[] = array(
        $changeset->newFileTreeIcon(),
        $title,
        $link,
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          null,
          pht('Change'),
          pht('Path'),
        ))
      ->setColumnClasses(
        array(
          null,
          null,
          'pri wide',
        ));

    return id(new PhabricatorApplicationSearchResultView())
      ->setTable($table);
  }

}
