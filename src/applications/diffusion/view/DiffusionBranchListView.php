<?php

final class DiffusionBranchListView extends DiffusionView {

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
    require_celerity_resource('diffusion-css');

    $buildables = $this->loadBuildables($commits);
    $have_builds = false;

    $can_close_branches = ($repository->isHg());

    Javelin::initBehavior('phabricator-tooltips');

    $doc_href = PhabricatorEnv::getDoclink('Diffusion User Guide: Autoclose');
    $list = id(new PHUIObjectItemListView())
      ->setFlush(true)
      ->addClass('diffusion-history-list')
      ->addClass('diffusion-branch-list');

    foreach ($this->branches as $branch) {
      $build_view = null;
      $button_bar = new PHUIButtonBarView();
      $commit = idx($commits, $branch->getCommitIdentifier());
      if ($commit) {
        $details = $commit->getSummary();
        $datetime = phabricator_datetime($commit->getEpoch(), $viewer);

        $buildable = idx($buildables, $commit->getPHID());
        if ($buildable) {
          $build_view = $this->renderBuildable($buildable, 'button');
        }
      } else {
        $datetime = null;
        $details = null;
      }

      if ($repository->supportsBranchComparison()) {
        $compare_uri = $drequest->generateURI(
          array(
            'action' => 'compare',
            'head' => $branch->getShortName(),
          ));
        $can_compare = ($branch->getShortName() != $current_branch);
        if ($can_compare) {
          $button_bar->addButton(
            id(new PHUIButtonView())
              ->setTag('a')
              ->setIcon('fa-balance-scale')
              ->setToolTip(pht('Compare'))
              ->setButtonType(PHUIButtonView::BUTTONTYPE_SIMPLE)
              ->setWorkflow(true)
              ->setHref($compare_uri));
        }
      }

      $browse_href = $drequest->generateURI(
        array(
          'action' => 'browse',
          'branch' => $branch->getShortName(),
        ));

      $button_bar->addButton(
        id(new PHUIButtonView())
          ->setIcon('fa-code')
          ->setHref($browse_href)
          ->setTag('a')
          ->setTooltip(pht('Browse'))
          ->setButtonType(PHUIButtonView::BUTTONTYPE_SIMPLE));

      $commit_link = $repository->getCommitURI(
        $branch->getCommitIdentifier());

      $commit_name = $repository->formatCommitName(
        $branch->getCommitIdentifier(), $local = true);

      $commit_tag = id(new PHUITagView())
        ->setName($commit_name)
        ->setHref($commit_link)
        ->setType(PHUITagView::TYPE_SHADE)
        ->setColor(PHUITagView::COLOR_INDIGO)
        ->setBorder(PHUITagView::BORDER_NONE)
        ->setSlimShady(true);
      $subhead = array($commit_tag, ' ', $details);

      $item = id(new PHUIObjectItemView())
        ->setHeader($branch->getShortName())
        ->setHref($drequest->generateURI(
          array(
            'action' => 'history',
            'branch' => $branch->getShortName(),
          )))
        ->setSubhead($subhead)
        ->setSideColumn(array(
          $build_view,
          $button_bar,
        ));

      if ($branch->getShortName() == $repository->getDefaultBranch()) {
        $item->setStatusIcon('fa-code-fork', pht('Default Branch'));
      }
      $item->addAttribute(array($datetime));

      if ($can_close_branches) {
        $fields = $branch->getRawFields();
        $closed = idx($fields, 'closed');
        if ($closed) {
          $status = pht('Branch Closed');
          $item->setDisabled(true);
        } else {
          $status = pht('Branch Open');
        }
        $item->addAttribute($status);
      }

      $list->addItem($item);

    }
    return $list;

  }
}
