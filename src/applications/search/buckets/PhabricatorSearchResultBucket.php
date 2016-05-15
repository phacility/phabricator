<?php

abstract class PhabricatorSearchResultBucket
  extends Phobject {

  private $pageSize;

  final public function setPageSize($page_size) {
    $this->pageSize = $page_size;
    return $this;
  }

  final public function getPageSize() {
    if ($this->pageSize === null) {
      return $this->getDefaultPageSize();
    }

    return $this->pageSize;
  }

  protected function getDefaultPageSize() {
    return 1000;
  }

  abstract public function getResultBucketName();

  final public function getResultBucketKey() {
    return $this->getPhobjectClassConstant('BUCKETKEY');
  }

}
