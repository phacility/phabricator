<?php

abstract class FeedConduitAPIMethod extends ConduitAPIMethod {

  final public function getApplication() {
    return PhabricatorApplication::getByClass('PhabricatorFeedApplication');
  }

}
