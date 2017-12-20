<?php

final class PhameBlogQuery extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $domain;
  private $statuses;

  private $needBloggers;
  private $needProfileImage;
  private $needHeaderImage;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withDomain($domain) {
    $this->domain = $domain;
    return $this;
  }

  public function withStatuses(array $status) {
    $this->statuses = $status;
    return $this;
  }

  public function needProfileImage($need) {
    $this->needProfileImage = $need;
    return $this;
  }

  public function needHeaderImage($need) {
    $this->needHeaderImage = $need;
    return $this;
  }

  public function newResultObject() {
    return new PhameBlog();
  }

  protected function loadPage() {
    return $this->loadStandardPage($this->newResultObject());
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->statuses !== null) {
      $where[] = qsprintf(
        $conn,
        'b.status IN (%Ls)',
        $this->statuses);
    }

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'b.id IN (%Ls)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'b.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->domain !== null) {
      $where[] = qsprintf(
        $conn,
        'b.domain = %s',
        $this->domain);
    }

    return $where;
  }

  protected function didFilterPage(array $blogs) {
    if ($this->needProfileImage) {
      $default = null;

      $file_phids = mpull($blogs, 'getProfileImagePHID');
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

      foreach ($blogs as $blog) {
        $file = idx($files, $blog->getProfileImagePHID());
        if (!$file) {
          if (!$default) {
            $default = PhabricatorFile::loadBuiltin(
              $this->getViewer(),
              'blog.png');
          }
          $file = $default;
        }
        $blog->attachProfileImageFile($file);
      }
    }

    if ($this->needHeaderImage) {
      $file_phids = mpull($blogs, 'getHeaderImagePHID');
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

      foreach ($blogs as $blog) {
        $file = idx($files, $blog->getHeaderImagePHID());
        if ($file) {
          $blog->attachHeaderImageFile($file);
        }
      }
    }
    return $blogs;
  }

  public function getQueryApplicationClass() {
    // TODO: Can we set this without breaking public blogs?
    return null;
  }

  protected function getPrimaryTableAlias() {
    return 'b';
  }

}
