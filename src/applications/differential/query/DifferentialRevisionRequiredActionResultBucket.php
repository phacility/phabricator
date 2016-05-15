<?php

final class DifferentialRevisionRequiredActionResultBucket
  extends DifferentialRevisionResultBucket {

  const BUCKETKEY = 'action';

  public function getResultBucketName() {
    return pht('Bucket by Required Action');
  }

}
