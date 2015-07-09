<?php

final class HeraldPreCommitRefAdapter extends HeraldPreCommitAdapter {

  const VALUE_REF_TYPE = 'value-ref-type';
  const VALUE_REF_CHANGE = 'value-ref-change';

  public function getAdapterContentName() {
    return pht('Commit Hook: Branches/Tags/Bookmarks');
  }

  public function getAdapterSortOrder() {
    return 2000;
  }

  public function getAdapterContentDescription() {
    return pht(
      "React to branches and tags being pushed to hosted repositories.\n".
      "Hook rules can block changes and send push summary mail.");
  }

  public function isPreCommitRefAdapter() {
    return true;
  }

  public function getHeraldName() {
    return pht('Push Log (Ref)');
  }

}
