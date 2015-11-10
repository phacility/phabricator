<?php

final class PhameBlogCreateCapability
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'phame.blog.default.create';

  public function getCapabilityName() {
    return pht('Can Create Blogs');
  }

  public function describeCapabilityRejection() {
    return pht('You do not have permission to create a blog.');
  }

}
