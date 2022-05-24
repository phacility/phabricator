<?php

final class PhamePostQuery extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $blogPHIDs;
  private $bloggerPHIDs;
  private $visibility;
  private $publishedAfter;
  private $phids;

  private $needHeaderImage;

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

  public function needHeaderImage($need) {
    $this->needHeaderImage = $need;
    return $this;
  }

  public function newResultObject() {
    return new PhamePost();
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

    if ($this->needHeaderImage) {
      $file_phids = mpull($posts, 'getHeaderImagePHID');
      $file_phids = array_filter($file_phids);
      if ($file_phids) {
        $files = id(new PhabricatorFileQuery())
          ->setParentQuery($this)
          ->setViewer($this->getViewer())
          ->withPHIDs($file_phids)
          ->execute();
        $files = mpull($files, null, 'getPHID');
      } else {
        $files = array();
      }

      foreach ($posts as $post) {
        $file = idx($files, $post->getHeaderImagePHID());
        if ($file) {
          $post->attachHeaderImageFile($file);
        }
      }
    }

    return $posts;
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'p.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'p.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->bloggerPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'p.bloggerPHID IN (%Ls)',
        $this->bloggerPHIDs);
    }

    if ($this->visibility !== null) {
      $where[] = qsprintf(
        $conn,
        'p.visibility IN (%Ld)',
        $this->visibility);
    }

    if ($this->publishedAfter !== null) {
      $where[] = qsprintf(
        $conn,
        'p.datePublished > %d',
        $this->publishedAfter);
    }

    if ($this->blogPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'p.blogPHID in (%Ls)',
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
        'table' => $this->getPrimaryTableAlias(),
        'column' => 'datePublished',
        'type' => 'int',
        'reverse' => false,
      ),
    );
  }

  protected function newPagingMapFromPartialObject($object) {
    return array(
      'id' => (int)$object->getID(),
      'datePublished' => (int)$object->getDatePublished(),
    );
  }

  public function getQueryApplicationClass() {
    // TODO: Does setting this break public blogs?
    return null;
  }

  protected function getPrimaryTableAlias() {
    return 'p';
  }

}
