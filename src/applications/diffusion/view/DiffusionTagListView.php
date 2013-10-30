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


    $rows = array();
    foreach ($this->tags as $tag) {
      $commit = idx($this->commits, $tag->getCommitIdentifier());

      $tag_link = phutil_tag(
        'a',
        array(
          'href' => $drequest->generateURI(
            array(
              'action' => 'browse',
              'commit' => $tag->getName(),
            )),
        ),
        $tag->getName());

      $commit_link = phutil_tag(
        'a',
        array(
          'href' => $drequest->generateURI(
            array(
              'action' => 'commit',
              'commit' => $tag->getCommitIdentifier(),
            )),
        ),
          $repository->formatCommitName(
            $tag->getCommitIdentifier()));

      $author = null;
      if ($commit && $commit->getAuthorPHID()) {
        $author = $this->handles[$commit->getAuthorPHID()]->renderLink();
      } else if ($commit && $commit->getCommitData()) {
        $author = self::renderName($commit->getCommitData()->getAuthorName());
      } else {
        $author = self::renderName($tag->getAuthor());
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

      $rows[] = array(
        $tag_link,
        $commit_link,
        $description,
        $author,
        phabricator_datetime($tag->getEpoch(), $this->user),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        pht('Tag'),
        pht('Commit'),
        pht('Description'),
        pht('Author'),
        pht('Created'),
      ));
    $table->setColumnClasses(
      array(
        'pri',
        '',
        'wide',
      ));
    return $table->render();
  }

}
