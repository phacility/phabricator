<?php

final class DiffusionBranchTableView extends DiffusionView {

  private $branches;
  private $commits = array();

  public function setBranches(array $branches) {
    assert_instances_of($branches, 'DiffusionBranchInformation');
    $this->branches = $branches;
    return $this;
  }

  public function setCommits(array $commits) {
    $this->commits = mpull($commits, null, 'getCommitIdentifier');
    return $this;
  }

  public function render() {
    $drequest = $this->getDiffusionRequest();
    $current_branch = $drequest->getBranch();

    $rows = array();
    $rowc = array();
    foreach ($this->branches as $branch) {
      $commit = idx($this->commits, $branch->getHeadCommitIdentifier());
      if ($commit) {
        $details = $commit->getSummary();
        $datetime = phabricator_datetime($commit->getEpoch(), $this->user);
      } else {
        $datetime = null;
        $details = null;
      }

      $rows[] = array(
        phutil_tag(
          'a',
          array(
            'href' => $drequest->generateURI(
              array(
                'action' => 'history',
                'branch' => $branch->getName(),
              ))
          ),
          pht('History')),
        phutil_tag(
          'a',
          array(
            'href' => $drequest->generateURI(
              array(
                'action' => 'browse',
                'branch' => $branch->getName(),
              )),
          ),
          $branch->getName()),
        self::linkCommit(
          $drequest->getRepository(),
          $branch->getHeadCommitIdentifier()),
        $datetime,
        AphrontTableView::renderSingleDisplayLine($details),
      );
      if ($branch->getName() == $current_branch) {
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
        pht('Modified'),
        pht('Details'),
      ));
    $view->setColumnClasses(
      array(
        '',
        'pri',
        '',
        '',
        'wide',
      ));
    $view->setRowClasses($rowc);
    return $view->render();
  }

}
