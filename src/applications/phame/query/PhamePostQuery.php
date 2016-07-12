<?php

final class PhamePostQuery extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $blogPHIDs;
  private $bloggerPHIDs;
  private $visibility;
  private $publishedAfter;
  private $phids;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withBloggerPHIDs(array $blogger_phids) {
    $this->bloggerPHIDs = $blogger_phids;
    return $this;
  }

  public function withBlogPHIDs(array $blog_phids) {
    $this->blogPHIDs = $blog_phids;
    return $this;
  }

  public function withVisibility(array $visibility) {
    $this->visibility = $visibility;
    return $this;
  }

  public function withPublishedAfter($time) {
    $this->publishedAfter = $time;
    return $this;
  }

  public function newResultObject() {
    return new PhamePost();
  }

  protected function loadPage() {
    return $this->loadStandardPage($this->newResultObject());
  }

  protected function willFilterPage(array $posts) {
    // We require blogs to do visibility checks, so load them unconditionally.
    $blog_phids = mpull($posts, 'getBlogPHID');

    $blogs = id(new PhameBlogQuery())
      ->setViewer($this->getViewer())
      ->needProfileImage(true)
      ->withPHIDs($blog_phids)
      ->execute();

    $blogs = mpull($blogs, null, 'getPHID');
    foreach ($posts as $key => $post) {
      $blog_phid = $post->getBlogPHID();

      $blog = idx($blogs, $blog_phid);
      if (!$blog) {
        $this->didRejectResult($post);
        unset($posts[$key]);
        continue;
      }

      $post->attachBlog($blog);
    }

    return $posts;
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids) {
      $where[] = qsprintf(
        $conn,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids) {
      $where[] = qsprintf(
        $conn,
        'phid IN (%Ls)',
        $this->phids);
    }

    if ($this->bloggerPHIDs) {
      $where[] = qsprintf(
        $conn,
        'bloggerPHID IN (%Ls)',
        $this->bloggerPHIDs);
    }

    if ($this->visibility) {
      $where[] = qsprintf(
        $conn,
        'visibility IN (%Ld)',
        $this->visibility);
    }

    if ($this->publishedAfter !== null) {
      $where[] = qsprintf(
        $conn,
        'datePublished > %d',
        $this->publishedAfter);
    }

    if ($this->blogPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'blogPHID in (%Ls)',
        $this->blogPHIDs);
    }

    return $where;
  }

  public function getBuiltinOrders() {
    return array(
      'datePublished' => array(
        'vector' => array('datePublished', 'id'),
        'name' => pht('Publish Date'),
      ),
    ) + parent::getBuiltinOrders();
  }

  public function getOrderableColumns() {
    return parent::getOrderableColumns() + array(
      'datePublished' => array(
        'column' => 'datePublished',
        'type' => 'int',
        'reverse' => false,
      ),
    );
  }

  protected function getPagingValueMap($cursor, array $keys) {
    $post = $this->loadCursorObject($cursor);

    $map = array(
      'datePublished' => $post->getDatePublished(),
      'id' => $post->getID(),
    );

    return $map;
  }

  public function getQueryApplicationClass() {
    // TODO: Does setting this break public blogs?
    return null;
  }

}
