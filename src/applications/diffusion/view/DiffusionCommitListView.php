<?php

final class DiffusionCommitListView extends AphrontView {

  private $commits = array();
  private $noDataString;

  public function setNoDataString($no_data_string) {
    $this->noDataString = $no_data_string;
    return $this;
  }

  public function setHeader($header) {
    $this->header = $header;
    return $this;
  }

  public function setCommits(array $commits) {
    assert_instances_of($commits, 'PhabricatorRepositoryCommit');
    $this->commits = mpull($commits, null, 'getPHID');
    return $this;
  }

  public function getCommits() {
    return $this->commits;
  }

  private function getCommitDescription($phid) {
    if ($this->commits === null) {
      return pht('(Unknown Commit)');
    }

    $commit = idx($this->commits, $phid);
    if (!$commit) {
      return pht('(Unknown Commit)');
    }

    $summary = $commit->getCommitData()->getSummary();
    if (strlen($summary)) {
      return $summary;
    }

    // No summary, so either this is still importing or just has an empty
    // commit message.

    if (!$commit->isImported()) {
      return pht('(Importing Commit...)');
    } else {
      return pht('(Untitled Commit)');
    }
  }

  public function render() {
    require_celerity_resource('diffusion-history-css');
    return $this->buildList();
  }

  public function buildList() {
    $viewer = $this->getViewer();
    $rowc = array();

    $phids = array();
    foreach ($this->getCommits() as $commit) {
      $phids[] = $commit->getPHID();

      $author_phid = $commit->getAuthorPHID();
      if ($author_phid) {
        $phids[] = $author_phid;
      }
    }

    $handles = $viewer->loadHandles($phids);

    $cur_date = 0;
    $list = null;
    $header = null;
    $view = array();
    foreach ($this->commits as $commit) {
      $new_date = date('Ymd', $commit->getEpoch());
      if ($cur_date != $new_date) {
        if ($list) {
          $view[] = id(new PHUIObjectBoxView())
            ->setHeader($header)
            ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
            ->setObjectList($list);
        }
        $date = ucfirst(
          phabricator_relative_date($commit->getEpoch(), $viewer));
        $header = id(new PHUIHeaderView())
          ->setHeader($date);
        $list = id(new PHUIObjectItemListView())
          ->setFlush(true)
          ->addClass('diffusion-history-list');
      }

      $commit_phid = $commit->getPHID();
      $commit_handle = $handles[$commit_phid];
      $committed = null;

      $commit_name = $commit_handle->getName();
      $commit_link = $commit_handle->getURI();
      $commit_desc = $this->getCommitDescription($commit_phid);
      $committed = phabricator_datetime($commit->getEpoch(), $viewer);

      $engine = PhabricatorMarkupEngine::newDifferentialMarkupEngine();
      $engine->setConfig('viewer', $viewer);
      $commit_data = $commit->getCommitData();
      $message = $commit_data->getCommitMessage();
      $message = $engine->markupText($message);
      $message = phutil_tag_div(
        'diffusion-history-message phabricator-remarkup', $message);

      $author_phid = $commit->getAuthorPHID();
      if ($author_phid) {
        $author_name = $handles[$author_phid]->renderLink();
        $author_image_uri = $handles[$author_phid]->getImageURI();
        $author_image_href = $handles[$author_phid]->getURI();
      } else {
        $author_name = $commit->getCommitData()->getAuthorName();
        $author_image_uri =
          celerity_get_resource_uri('/rsrc/image/people/user0.png');
        $author_image_href = null;
      }

      $commit_tag = id(new PHUITagView())
        ->setName($commit_name)
        ->setType(PHUITagView::TYPE_SHADE)
        ->setColor(PHUITagView::COLOR_INDIGO)
        ->setSlimShady(true);

      $item = id(new PHUIObjectItemView())
        ->setHeader($commit_desc)
        ->setHref($commit_link)
        ->setDisabled($commit->isUnreachable())
        ->setDescription($message)
        ->setImageURI($author_image_uri)
        ->setImageHref($author_image_href)
        ->addByline(pht('Author: %s', $author_name))
        ->addIcon('none', $committed)
        ->addAttribute($commit_tag);

      $list->addItem($item);
      $cur_date = $new_date;
    }

    if (!$view) {
      $list = id(new PHUIObjectItemListView())
        ->setFlush(true)
        ->setNoDataString($this->noDataString);

      $view = id(new PHUIObjectBoxView())
        ->setHeaderText(pht('Recent Commits'))
        ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
        ->setObjectList($list);
    }

    return $view;
  }

}
