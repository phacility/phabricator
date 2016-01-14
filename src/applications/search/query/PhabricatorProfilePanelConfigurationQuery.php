<?php

final class PhabricatorProfilePanelConfigurationQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $profilePHIDs;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withProfilePHIDs(array $phids) {
    $this->profilePHIDs = $phids;
    return $this;
  }

  public function newResultObject() {
    return new PhabricatorProfilePanelConfiguration();
  }

  protected function loadPage() {
    return $this->loadStandardPage($this->newResultObject());
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'phid IN (%Ls)',
        $this->phids);
    }

    if ($this->profilePHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'profilePHID IN (%Ls)',
        $this->profilePHIDs);
    }

    return $where;
  }

  protected function willFilterPage(array $page) {
    $panels = PhabricatorProfilePanel::getAllPanels();
    foreach ($page as $key => $panel) {
      $panel_type = idx($panels, $panel->getPanelKey());
      if (!$panel_type) {
        $this->didRejectResult($panel);
        unset($page[$key]);
        continue;
      }
      $panel->attachPanel($panel_type);
    }

    if (!$page) {
      return array();
    }

    $profile_phids = mpull($page, 'getProfilePHID');

    $profiles = id(new PhabricatorObjectQuery())
      ->setViewer($this->getViewer())
      ->setParentQuery($this)
      ->withPHIDs($profile_phids)
      ->execute();
    $profiles = mpull($profiles, null, 'getPHID');

    foreach ($page as $key => $panel) {
      $profile = idx($profiles, $panel->getProfilePHID());
      if (!$profile) {
        $this->didRejectResult($panel);
        unset($page[$key]);
        continue;
      }
      $panel->attachProfileObject($profile);
    }

    return $page;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorSearchApplication';
  }

}
