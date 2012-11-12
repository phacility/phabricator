<?php

/**
 * TODO: Facebook extends this (I think?), but should it?
 */
class PhabricatorRepositoryDefaultCommitMessageDetailParser
  extends PhabricatorRepositoryCommitMessageDetailParser {

  public function parseCommitDetails() {
    $commit = $this->getCommit();
    $data = $this->getCommitData();

    $details = nonempty($data->getCommitDetails(), array());
    $message = $data->getCommitMessage();
    $author_name = $data->getAuthorName();

    // TODO: Some day, it would be good to drive all of this via
    // DifferentialFieldSpecification configuration directly.

    $match = null;

    if (preg_match(
      '/^\s*Differential Revision:\s*(\S+)\s*$/mi',
      $message,
      $match)) {

      // NOTE: We now accept ONLY full URIs because if we accept numeric IDs
      // then anyone importing the Phabricator repository will have their
      // first few thousand revisions marked closed. This does mean that
      // some older revisions won't re-parse correctly, but that shouldn't
      // really affect anyone. If necessary, an install can extend the parser
      // and restore the older, more-liberal parsing fairly easily.

      $id = DifferentialRevisionIDFieldSpecification::parseRevisionIDFromURI(
        $match[1]);
      if ($id) {
        $details['differential.revisionID'] = $id;
        $revision = id(new DifferentialRevision())->load($id);
        if ($revision) {
          $details['differential.revisionPHID'] = $revision->getPHID();
        }
      }
    }

    if (preg_match(
      '/^\s*Reviewed By:\s*(\S+)\s*$/mi',
      $message,
      $match)) {
      $details['reviewerName'] = $match[1];

      $reviewer_phid = $this->resolveUserPHID($details['reviewerName']);
      if ($reviewer_phid) {
        $details['reviewerPHID'] = $reviewer_phid;
      } else {
        unset($details['reviewerPHID']);
      }
    } else {
      unset($details['reviewerName']);
      unset($details['reviewerPHID']);
    }

    $author_phid = $this->resolveUserPHID($author_name);
    if ($author_phid) {
      $details['authorPHID'] = $author_phid;
    } else {
      unset($details['authorPHID']);
    }

    if (isset($details['committer'])) {
      $committer_phid = $this->resolveUserPHID($details['committer']);
      if ($committer_phid) {
        $details['committerPHID'] = $committer_phid;
      } else {
        unset($details['committerPHID']);
      }
    }

    $data->setCommitDetails($details);
  }

}
