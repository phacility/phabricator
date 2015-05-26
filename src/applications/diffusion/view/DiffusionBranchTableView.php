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

    $can_close_branches = ($repository->isHg());

    Javelin::initBehavior('phabricator-tooltips');

    $doc_href = PhabricatorEnv::getDoclink('Diffusion User Guide: Autoclose');

    $rows = array();
    $rowc = array();
    foreach ($this->branches as $branch) {
      $commit = idx($this->commits, $branch->getCommitIdentifier());
      if ($commit) {
        $details = $commit->getSummary();
        $datetime = phabricator_datetime($commit->getEpoch(), $this->user);
      } else {
        $datetime = null;
        $details = null;
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
        ->setIconFont($icon)
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
        phutil_tag(
          'a',
          array(
            'href' => $drequest->generateURI(
              array(
                'action' => 'history',
                'branch' => $branch->getShortName(),
              )),
          ),
          pht('History')),
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
        $status,
        $status_icon,
        $datetime,
        AphrontTableView::renderSingleDisplayLine($details),
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
        pht('History'),
        pht('Branch'),
        pht('Head'),
        pht('State'),
        pht(''),
        pht('Modified'),
        pht('Details'),
      ));
    $view->setColumnClasses(
      array(
        '',
        'pri',
        '',
        '',
        '',
        '',
        'wide',
      ));
    $view->setColumnVisibility(
      array(
        true,
        true,
        true,
        $can_close_branches,
      ));
    $view->setRowClasses($rowc);
    return $view->render();
  }

}
