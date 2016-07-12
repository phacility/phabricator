<?php

final class DiffusionBranchTableView extends DiffusionView {

  private $branches;
  private $commits = array();

  public function setBranches(array $branches) {
    assert_instances_of($branches, 'DiffusionRepositoryRef');
    $this->branches = $branches;
    return $this;
  }

  public function setCommits(array $commits) {
    assert_instances_of($commits, 'PhabricatorRepositoryCommit');
    $this->commits = mpull($commits, null, 'getCommitIdentifier');
    return $this;
  }

  public function render() {
    $drequest = $this->getDiffusionRequest();
    $current_branch = $drequest->getBranch();
    $repository = $drequest->getRepository();
    $commits = $this->commits;
    $viewer = $this->getUser();

    $buildables = $this->loadBuildables($commits);
    $have_builds = false;

    $can_close_branches = ($repository->isHg());

    Javelin::initBehavior('phabricator-tooltips');

    $doc_href = PhabricatorEnv::getDoclink('Diffusion User Guide: Autoclose');

    $rows = array();
    $rowc = array();
    foreach ($this->branches as $branch) {
      $commit = idx($commits, $branch->getCommitIdentifier());
      if ($commit) {
        $details = $commit->getSummary();
        $datetime = $viewer->formatShortDateTime($commit->getEpoch());
        $buildable = idx($buildables, $commit->getPHID());
        if ($buildable) {
          $build_status = $this->renderBuildable($buildable);
          $have_builds = true;
        } else {
          $build_status = null;
        }
      } else {
        $datetime = null;
        $details = null;
        $build_status = null;
      }

      switch ($repository->shouldSkipAutocloseBranch($branch->getShortName())) {
        case PhabricatorRepository::BECAUSE_REPOSITORY_IMPORTING:
          $icon = 'fa-times bluegrey';
          $tip = pht('Repository Importing');
          break;
        case PhabricatorRepository::BECAUSE_AUTOCLOSE_DISABLED:
          $icon = 'fa-times bluegrey';
          $tip = pht('Repository Autoclose Disabled');
          break;
        case PhabricatorRepository::BECAUSE_BRANCH_UNTRACKED:
          $icon = 'fa-times bluegrey';
          $tip = pht('Branch Untracked');
          break;
        case PhabricatorRepository::BECAUSE_BRANCH_NOT_AUTOCLOSE:
          $icon = 'fa-times bluegrey';
          $tip = pht('Branch Autoclose Disabled');
          break;
        case null:
          $icon = 'fa-check bluegrey';
          $tip = pht('Autoclose Enabled');
          break;
        default:
          $icon = 'fa-question';
          $tip = pht('Status Unknown');
          break;
      }

      $status_icon = id(new PHUIIconView())
        ->setIcon($icon)
        ->addSigil('has-tooltip')
        ->setHref($doc_href)
        ->setMetadata(
          array(
            'tip' => $tip,
            'size' => 200,
          ));

      $fields = $branch->getRawFields();
      $closed = idx($fields, 'closed');
      if ($closed) {
        $status = pht('Closed');
      } else {
        $status = pht('Open');
      }

      $rows[] = array(
        $this->linkBranchHistory($branch->getShortName()),
        phutil_tag(
          'a',
          array(
            'href' => $drequest->generateURI(
              array(
                'action' => 'browse',
                'branch' => $branch->getShortName(),
              )),
          ),
          $branch->getShortName()),
        self::linkCommit(
          $drequest->getRepository(),
          $branch->getCommitIdentifier()),
        $build_status,
        $status,
        AphrontTableView::renderSingleDisplayLine($details),
        $status_icon,
        $datetime,
      );
      if ($branch->getShortName() == $current_branch) {
        $rowc[] = 'highlighted';
      } else {
        $rowc[] = null;
      }
    }

    $view = new AphrontTableView($rows);
    $view->setHeaders(
      array(
        null,
        pht('Branch'),
        pht('Head'),
        null,
        pht('State'),
        pht('Details'),
        null,
        pht('Committed'),
      ));
    $view->setColumnClasses(
      array(
        '',
        'pri',
        '',
        'icon',
        '',
        'wide',
        '',
        'right',
      ));
    $view->setColumnVisibility(
      array(
        true,
        true,
        true,
        $have_builds,
        $can_close_branches,
      ));
    $view->setRowClasses($rowc);
    return $view->render();
  }


}
