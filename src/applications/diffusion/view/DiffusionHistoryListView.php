<?php

final class DiffusionHistoryListView extends DiffusionHistoryView {

  public function render() {
    $drequest = $this->getDiffusionRequest();
    $viewer = $this->getUser();
    $repository = $drequest->getRepository();

    require_celerity_resource('diffusion-css');
    Javelin::initBehavior('phabricator-tooltips');

    $buildables = $this->loadBuildables(
      mpull($this->getHistory(), 'getCommit'));

    $show_revisions = PhabricatorApplication::isClassInstalledForViewer(
      'PhabricatorDifferentialApplication',
      $viewer);

    $handles = $viewer->loadHandles($this->getRequiredHandlePHIDs());

    $show_builds = PhabricatorApplication::isClassInstalledForViewer(
      'PhabricatorHarbormasterApplication',
      $this->getUser());

    $cur_date = null;
    $view = array();
    foreach ($this->getHistory() as $history) {
      $epoch = $history->getEpoch();
      $new_date = phabricator_date($history->getEpoch(), $viewer);
      if ($cur_date !== $new_date) {
        $date = ucfirst(
          phabricator_relative_date($history->getEpoch(), $viewer));
        $header = id(new PHUIHeaderView())
          ->setHeader($date);
        $list = id(new PHUIObjectItemListView())
          ->setFlush(true)
          ->addClass('diffusion-history-list');

        $view[] = id(new PHUIObjectBoxView())
          ->setHeader($header)
          ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
          ->addClass('diffusion-mobile-view')
          ->setObjectList($list);
      }

      if ($epoch) {
        $committed = $viewer->formatShortDateTime($epoch);
      } else {
        $committed = null;
      }

      $data = $history->getCommitData();
      $author_phid = $committer = $committer_phid = null;
      if ($data) {
        $author_phid = $data->getCommitDetail('authorPHID');
        $committer_phid = $data->getCommitDetail('committerPHID');
        $committer = $data->getCommitDetail('committer');
      }

      if ($author_phid && isset($handles[$author_phid])) {
        $author_name = $handles[$author_phid]->renderLink();
        $author_image = $handles[$author_phid]->getImageURI();
      } else {
        $author_name = self::renderName($history->getAuthorName());
        $author_image =
          celerity_get_resource_uri('/rsrc/image/people/user0.png');
      }

      $different_committer = false;
      if ($committer_phid) {
        $different_committer = ($committer_phid != $author_phid);
      } else if ($committer != '') {
        $different_committer = ($committer != $history->getAuthorName());
      }
      if ($different_committer) {
        if ($committer_phid && isset($handles[$committer_phid])) {
          $committer = $handles[$committer_phid]->renderLink();
        } else {
          $committer = self::renderName($committer);
        }
        $author_name = hsprintf('%s / %s', $author_name, $committer);
      }

      // We can show details once the message and change have been imported.
      $partial_import = PhabricatorRepositoryCommit::IMPORTED_MESSAGE |
                        PhabricatorRepositoryCommit::IMPORTED_CHANGE;

      $commit = $history->getCommit();
      if ($commit && $commit->isPartiallyImported($partial_import) && $data) {
        $commit_desc = $history->getSummary();
      } else {
        $commit_desc = phutil_tag('em', array(), pht("Importing\xE2\x80\xA6"));
      }

      $browse_button = $this->linkBrowse(
        $history->getPath(),
        array(
          'commit' => $history->getCommitIdentifier(),
          'branch' => $drequest->getBranch(),
          'type' => $history->getFileType(),
        ),
        true);

      $diff_tag = null;
      if ($show_revisions && $commit) {
        $d_id = idx($this->getRevisions(), $commit->getPHID());
        if ($d_id) {
          $diff_tag = id(new PHUITagView())
            ->setName('D'.$d_id)
            ->setType(PHUITagView::TYPE_SHADE)
            ->setColor(PHUITagView::COLOR_BLUE)
            ->setHref('/D'.$d_id)
            ->setBorder(PHUITagView::BORDER_NONE)
            ->setSlimShady(true);
          }
      }

      $build_view = null;
      if ($show_builds) {
        $buildable = idx($buildables, $commit->getPHID());
        if ($buildable !== null) {
          $build_view = $this->renderBuildable($buildable, 'button');
        }
      }

      $message = null;
      $commit_link = $repository->getCommitURI(
        $history->getCommitIdentifier());

      $commit_name = $repository->formatCommitName(
        $history->getCommitIdentifier(), $local = true);

      $committed = phabricator_datetime($commit->getEpoch(), $viewer);
      $author_name = phutil_tag(
        'strong',
        array(
          'class' => 'diffusion-history-author-name',
        ),
        $author_name);
      $authored = pht('%s on %s.', $author_name, $committed);

      $commit_tag = id(new PHUITagView())
        ->setName($commit_name)
        ->setType(PHUITagView::TYPE_SHADE)
        ->setColor(PHUITagView::COLOR_INDIGO)
        ->setBorder(PHUITagView::BORDER_NONE)
        ->setSlimShady(true);

      $item = id(new PHUIObjectItemView())
        ->setHeader($commit_desc)
        ->setHref($commit_link)
        ->setDisabled($commit->isUnreachable())
        ->setDescription($message)
        ->setImageURI($author_image)
        ->addAttribute(array($commit_tag, ' ', $diff_tag)) // For Copy Pasta
        ->addAttribute($authored)
        ->setSideColumn(array(
          $build_view,
          $browse_button,
        ));

      $list->addItem($item);
      $cur_date = $new_date;
    }


    return $view;
  }

}
