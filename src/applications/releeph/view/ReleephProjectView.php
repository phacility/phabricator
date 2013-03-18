<?php

final class ReleephProjectView extends AphrontView {

  private $showOpenBranches = true;
  private $releephProject;
  private $releephBranches;

  public function setShowOpenBranches($active) {
    $this->showOpenBranches = $active;
    return $this;
  }

  public function setReleephProject($releeph_project) {
    $this->releephProject = $releeph_project;
    return $this;
  }

  public function setBranches($branches) {
    $this->releephBranches = $branches;
    return $this;
  }

  public function render() {
    $releeph_project = $this->releephProject;

    if ($this->showOpenBranches) {
      $releeph_branches = mfilter($this->releephBranches, 'getIsActive');
    } else {
      $releeph_branches = mfilter($this->releephBranches, 'getIsActive', true);
    }

    // Load all relevant PHID handles
    $phids = array_merge(
      array(
        $this->releephProject->getPHID(),
        $this->releephProject->getRepositoryPHID(),
      ),
      mpull($releeph_branches, 'getCreatedByUserPHID'),
      mpull($releeph_branches, 'getCutPointCommitPHID'),
      $releeph_project->getPushers());
    $handles = id(new PhabricatorObjectHandleData($phids))
      ->setViewer($this->getUser())
      ->loadHandles();

    // Sort branches, which requires the handles above
    $releeph_branches = self::sortBranches($releeph_branches, $handles);

    // The header
    $repository_phid = $releeph_project->getRepositoryPHID();

    $header = hsprintf(
      '%s in %s repository',
      $releeph_project->getName(),
      $handles[$repository_phid]->renderLink());

    if ($this->showOpenBranches) {
      $view_other_link = phutil_tag(
        'a',
        array(
          'href' => $releeph_project->getURI('closedbranches/'),
        ),
        'View closed branches');
    } else {
      $view_other_link = phutil_tag(
        'a',
        array(
          'href' => $releeph_project->getURI(),
        ),
        'View open branches');
    }

    $header = hsprintf("%s &middot; %s", $header, $view_other_link);

    // The "create branch" button
    $create_branch_url = $releeph_project->getURI('cutbranch/');

    // Pushers info
    $pushers_info = array();
    $pushers = $releeph_project->getPushers();
    require_celerity_resource('releeph-project');
    if ($pushers) {
      $pushers_info[] = phutil_tag('h2', array(), 'Pushers');
      foreach ($pushers as $user_phid) {
        $handle = $handles[$user_phid];
        $div = phutil_tag(
          'div',
          array(
            'class' => 'releeph-pusher',
            'style' => 'background-image: url('.$handle->getImageURI().');',
          ),
          phutil_tag(
            'div',
            array(
              'class' => 'releeph-pusher-body',
            ),
            $handles[$user_phid]->renderLink()));
        $pushers_info[] = $div;
      }

      $pushers_info[] = hsprintf('<div style="clear: both;"></div>');
    }

    // Put it all together
    $panel = id(new AphrontPanelView())
      ->setHeader($header)
      ->appendChild(phutil_implode_html('', $pushers_info));

    foreach ($releeph_branches as $ii => $releeph_branch) {
      $box = id(new ReleephBranchBoxView())
        ->setUser($this->user)
        ->setHandles($handles)
        ->setReleephBranch($releeph_branch)
        ->setNamed();

      if ($ii === 0) {
        $box->setLatest();
      }
      $panel->appendChild($box);
    }

    return $panel->render();
  }

  /**
   * Sort branches by the point at which they were cut, newest cut points
   * first.
   *
   * If branches share a cut point, sort newest branch first.
   */
  private static function sortBranches($branches, $handles) {
    // Group by commit phid
    $groups = mgroup($branches, 'getCutPointCommitPHID');

    // Convert commit phid to a commit timestamp
    $ar = array();
    foreach ($groups as $cut_phid => $group) {
      $handle = $handles[$cut_phid];
      // Pack (timestamp, group-with-this-timestamp) pairs into $ar
      $ar[] = array(
        $handle->getTimestamp(),
        msort($group, 'getDateCreated')
      );
    }

    $branches = array();
    // Sort by timestamp, pull groups, and flatten into one big group
    foreach (ipull(isort($ar, 0), 1) as $group) {
      $branches = array_merge($branches, $group);
    }

    return array_reverse($branches);
  }

}
