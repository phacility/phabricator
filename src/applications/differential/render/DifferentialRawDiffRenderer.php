<?php

final class DifferentialRawDiffRenderer extends Phobject {

  private $changesets;
  private $format = 'unified';
  private $viewer;

  public function setFormat($format) {
    $this->format = $format;
    return $this;
  }

  public function getFormat() {
    return $this->format;
  }

  public function setChangesets(array $changesets) {
    assert_instances_of($changesets, 'DifferentialChangeset');

    $this->changesets = $changesets;
    return $this;
  }

  public function getChangesets() {
    return $this->changesets;
  }

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function buildPatch() {
    $diff = new DifferentialDiff();
    $diff->attachChangesets($this->getChangesets());

    $raw_changes = $diff->buildChangesList();
    $changes = array();
    foreach ($raw_changes as $changedict) {
      $changes[] = ArcanistDiffChange::newFromDictionary($changedict);
    }

    $viewer = $this->getViewer();
    $loader = id(new PhabricatorFileBundleLoader())
      ->setViewer($viewer);

    $bundle = ArcanistBundle::newFromChanges($changes);
    $bundle->setLoadFileDataCallback(array($loader, 'loadFileData'));

    $format = $this->getFormat();
    switch ($format) {
      case 'git':
        return $bundle->toGitPatch();
        break;
      case 'unified':
      default:
        return $bundle->toUnifiedDiff();
        break;
    }
  }
}
