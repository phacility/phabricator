<?php

final class DiffusionTagListView extends DiffusionView {

  private $tags;
  private $commits = array();
  private $handles = array();

  public function setTags($tags) {
    $this->tags = $tags;
    return $this;
  }

  public function setCommits(array $commits) {
    $this->commits = mpull($commits, null, 'getCommitIdentifier');
    return $this;
  }

  public function setHandles(array $handles) {
    $this->handles = $handles;
    return $this;
  }

  public function getRequiredHandlePHIDs() {
    return array_filter(mpull($this->commits, 'getAuthorPHID'));
  }

  public function render() {
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();
    $viewer = $this->getViewer();
    require_celerity_resource('diffusion-css');

    $buildables = $this->loadBuildables($this->commits);

    $list = id(new PHUIObjectItemListView())
      ->setFlush(true)
      ->addClass('diffusion-history-list');
    foreach ($this->tags as $tag) {
      $commit = idx($this->commits, $tag->getCommitIdentifier());
      $button_bar = new PHUIButtonBarView();

      $tag_href = $drequest->generateURI(
        array(
          'action' => 'history',
          'commit' => $tag->getName(),
        ));

      $commit_href = $drequest->generateURI(
        array(
          'action' => 'commit',
          'commit' => $tag->getCommitIdentifier(),
        ));

      if ($commit) {
        $author = $this->renderAuthor($tag, $commit);
      } else {
        $author = null;
      }

      $description = null;
      if ($tag->getType() == 'git/tag') {
        // In Git, a tag may be a "real" tag, or just a reference to a commit.
        // If it's a real tag, use the message on the tag, since this may be
        // unique data which isn't otherwise available.
        $description = $tag->getDescription();
      } else {
        if ($commit) {
          $description = $commit->getSummary();
        } else {
          $description = $tag->getDescription();
        }
      }

      $build_view = null;
      if ($commit) {
        $buildable = idx($buildables, $commit->getPHID());
        if ($buildable) {
          $build_view = $this->renderBuildable($buildable, 'button');
        }
      }

      if ($repository->supportsBranchComparison()) {
        $compare_uri = $drequest->generateURI(
          array(
            'action' => 'compare',
            'head' => $tag->getName(),
          ));

        $button_bar->addButton(
          id(new PHUIButtonView())
            ->setTag('a')
            ->setIcon('fa-balance-scale')
            ->setToolTip(pht('Compare'))
            ->setButtonType(PHUIButtonView::BUTTONTYPE_SIMPLE)
            ->setWorkflow(true)
            ->setHref($compare_uri));
      }

      $commit_name = $repository->formatCommitName(
        $tag->getCommitIdentifier(), $local = true);

      $browse_href = $drequest->generateURI(
        array(
          'action' => 'browse',
          'commit' => $tag->getName(),
        ));

      $button_bar->addButton(
        id(new PHUIButtonView())
          ->setTooltip(pht('Browse'))
          ->setIcon('fa-code')
          ->setHref($browse_href)
          ->setTag('a')
          ->setButtonType(PHUIButtonView::BUTTONTYPE_SIMPLE));

      $commit_tag = id(new PHUITagView())
        ->setName($commit_name)
        ->setHref($commit_href)
        ->setType(PHUITagView::TYPE_SHADE)
        ->setColor(PHUITagView::COLOR_INDIGO)
        ->setBorder(PHUITagView::BORDER_NONE)
        ->setSlimShady(true);

      $item = id(new PHUIObjectItemView())
        ->setHeader($tag->getName())
        ->setHref($tag_href)
        ->addAttribute(array($commit_tag))
        ->addAttribute($description)
        ->setSideColumn(array(
          $build_view,
          $button_bar,
        ));

      if ($author) {
        $item->addAttribute($author);
      }

      $list->addItem($item);
    }

    return $list;
  }

  private function renderAuthor(
    DiffusionRepositoryTag $tag,
    PhabricatorRepositoryCommit $commit) {
    $viewer = $this->getViewer();

    if ($commit->getAuthorPHID()) {
      $author = $this->handles[$commit->getAuthorPHID()]->renderLink();
    } else if ($commit->getCommitData()) {
      $author = self::renderName($commit->getCommitData()->getAuthorName());
    } else {
      $author = self::renderName($tag->getAuthor());
    }

    $committed = phabricator_datetime($commit->getEpoch(), $viewer);
    $author_name = phutil_tag(
      'strong',
      array(
        'class' => 'diffusion-history-author-name',
      ),
      $author);

    return pht('%s on %s.', $author_name, $committed);
  }

}
