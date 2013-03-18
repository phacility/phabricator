<?php

final class ReleephBranchBoxView extends AphrontView {

  private $releephBranch;
  private $isLatest = false;
  private $isNamed = false;
  private $handles;

  public function setReleephBranch(ReleephBranch $br) {
    $this->releephBranch = $br;
    return $this;
  }

  // Primary highlighted branch
  public function setLatest() {
    $this->isLatest = true;
    return $this;
  }

  // Secondary highlighted branch(es)
  public function setNamed() {
    $this->isNamed = true;
    return $this;
  }

  public function setHandles($handles) {
    $this->handles = $handles;
    return $this;
  }

  public function render() {
    $br = $this->releephBranch;

    require_celerity_resource('releeph-branch');
    return phutil_tag(
      'div',
      array(
        'class' => 'releeph-branch-box'.
                   ($this->isNamed  ? ' releeph-branch-box-named'  : '').
                   ($this->isLatest ? ' releeph-branch-box-latest' : ''),
      ),
      array(
        $this->renderNames(),
        $this->renderDatesTable(),
        // "float: right" means the ordering here is weird
        $this->renderButtons(),
        $this->renderStatisticsTable(),
        phutil_tag(
          'div',
          array(
            'style' => 'clear:both;',
          ),
          '')));
  }

  private function renderNames() {
    $br = $this->releephBranch;

    return phutil_tag(
      'div',
      array(
        'class' => 'names',
      ),
      array(
        phutil_tag(
          'h1',
          array(),
          $br->getDisplayName()),
        phutil_tag(
          'h2',
          array(),
          $br->getName())));
  }

  private function renderDatesTable() {
    $br = $this->releephBranch;
    $branch_commit_handle = $this->handles[$br->getCutPointCommitPHID()];

    $properties = array();
    $properties['Created by'] =

    $cut_age = phabricator_format_relative_time(
      time() - $branch_commit_handle->getTimestamp());

    return phutil_tag(
      'div',
      array(
        'class' => 'date-info',
      ),
      array(
        $this->handles[$br->getCreatedByUserPHID()]->renderLink(),
        phutil_tag('br'),
        phutil_tag(
          'a',
          array(
            'href' => $branch_commit_handle->getURI(),
          ),
          $cut_age.' old')));
  }

  private function renderStatisticsTable() {
    $statistics = array();

    $requests = $this->releephBranch->loadReleephRequests($this->getUser());
    foreach ($requests as $request) {
      $status = $request->getStatus();
      if (!isset($statistics[$status])) {
        $statistics[$status] = 0;
      }
      $statistics[$status]++;
    }

    static $col_groups = 3;

    $cells = array();
    foreach ($statistics as $status => $count) {
      $description = ReleephRequest::getStatusDescriptionFor($status);
      $cells[] = phutil_tag('th', array(), $count);
      $cells[] = phutil_tag('td', array(), $description);
    }

    $rows = array();
    while ($cells) {
      $row_cells = array();
      for ($ii = 0; $ii < 2 * $col_groups; $ii++) {
        $row_cells[] = array_shift($cells);
      }
      $rows[] = phutil_tag('tr', array(), $row_cells);
    }

    if (!$rows) {
      $rows = hsprintf('<tr><th></th><td>%s</td></tr>', 'none');
    }

    return phutil_tag(
      'div',
      array(
        'class' => 'request-statistics',
      ),
      phutil_tag(
        'table',
        array(),
        $rows));
  }

  private function renderButtons() {
    $br = $this->releephBranch;

    $buttons = array();

    $buttons[] = phutil_tag(
      'a',
      array(
        'class' => 'small grey button',
        'href'  => $br->getURI(),
      ),
      'View Requests');

    $repo = $br->loadReleephProject()->loadPhabricatorRepository();
    if (!$repo) {
      $buttons[] = phutil_tag(
        'a',
        array(
          'class' => 'small button disabled',
        ),
        "Diffusion \xE2\x86\x97");
    } else {
      $diffusion_request = DiffusionRequest::newFromDictionary(array(
        'repository' => $repo,
      ));
      $diffusion_branch_uri = $diffusion_request->generateURI(array(
        'action' => 'branch',
        'branch' => $br->getName(),
      ));
      $diffusion_button_class = 'small grey button';

      $buttons[] = phutil_tag(
        'a',
        array(
          'class'  => $diffusion_button_class,
          'target' => '_blank',
          'href'   => $diffusion_branch_uri,
        ),
        "Diffusion \xE2\x86\x97");
    }

    $releeph_project = $br->loadReleephProject();
    if (!$releeph_project->getPushers() ||
        $releeph_project->isPusher($this->user)) {

      $buttons[] = phutil_tag(
        'a',
        array(
          'class' => 'small blue button',
          'href'  => $br->getURI('edit/'),
        ),
        'Edit');

      if ($br->isActive()) {
        $button_text = "Close";
        $href = $br->getURI('close/');
      } else {
        $button_text = "Re-open";
        $href = $br->getURI('re-open/');
      }
      $buttons[] = javelin_tag(
        'a',
        array(
          'class' => 'small blue button',
          'href'  => $href,
          'sigil' => 'workflow',
        ),
        $button_text);
    }

    return phutil_tag(
      'div',
      array(
        'class' => 'buttons',
      ),
      $buttons);
  }

}
