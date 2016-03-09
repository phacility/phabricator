<?php

interface PhabricatorSubscribableInterface {

  /**
   * Return true to indicate that the given PHID is automatically subscribed
   * to the object (for example, they are the author or in some other way
   * irrevocably a subscriber). This will, e.g., cause the UI to render
   * "Automatically Subscribed" instead of "Subscribe".
   *
   * @param PHID  PHID (presumably a user) to test for automatic subscription.
   * @return bool True if the object/user is automatically subscribed.
   */
  public function isAutomaticallySubscribed($phid);

}

// TEMPLATE IMPLEMENTATION /////////////////////////////////////////////////////

/* -(  PhabricatorSubscribableInterface  )----------------------------------- */
/*

  public function isAutomaticallySubscribed($phid) {
    return false;
  }

*/
