<?php

final class PHUIDiffInlineThreader extends Phobject {

  public function reorderAndThreadCommments(array $comments) {
    $comments = msort($comments, 'getID');

    // Build an empty map of all the comments we actually have. If a comment
    // is a reply but the parent has gone missing, we don't want it to vanish
    // completely.
    $comment_phids = mpull($comments, 'getPHID');
    $replies = array_fill_keys($comment_phids, array());

    // Now, remove all comments which are replies, leaving only the top-level
    // comments.
    foreach ($comments as $key => $comment) {
      $reply_phid = $comment->getReplyToCommentPHID();
      if (isset($replies[$reply_phid])) {
        $replies[$reply_phid][] = $comment;
        unset($comments[$key]);
      }
    }

    // For each top level comment, add the comment, then add any replies
    // to it. Do this recursively so threads are shown in threaded order.
    $results = array();
    foreach ($comments as $comment) {
      $results[] = $comment;
      $phid = $comment->getPHID();
      $descendants = $this->getInlineReplies($replies, $phid, 1);
      foreach ($descendants as $descendant) {
        $results[] = $descendant;
      }
    }

    // If we have anything left, they were cyclic references. Just dump
    // them in a the end. This should be impossible, but users are very
    // creative.
    foreach ($replies as $phid => $comments) {
      foreach ($comments as $comment) {
        $results[] = $comment;
      }
    }

    return $results;
  }

  private function getInlineReplies(array &$replies, $phid, $depth) {
    $comments = idx($replies, $phid, array());
    unset($replies[$phid]);

    $results = array();
    foreach ($comments as $comment) {
      $results[] = $comment;
      $descendants = $this->getInlineReplies(
        $replies,
        $comment->getPHID(),
        $depth + 1);
      foreach ($descendants as $descendant) {
        $results[] = $descendant;
      }
    }

    return $results;
  }
}
