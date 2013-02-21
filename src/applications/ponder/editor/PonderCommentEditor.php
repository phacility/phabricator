<?php

final class PonderCommentEditor extends PhabricatorEditor {

  private $question;
  private $comment;
  private $targetPHID;
  private $shouldEmail = true;

  public function setComment(PonderComment $comment) {
    $this->comment = $comment;
    return $this;
  }

  public function setQuestion(PonderQuestion $question) {
    $this->question = $question;
    return $this;
  }

  public function setTargetPHID($target) {
    $this->targetPHID = $target;
    return $this;
  }

  public function save() {
    $actor = $this->requireActor();
    if (!$this->comment) {
      throw new Exception("Must set comment before saving it");
    }
    if (!$this->question) {
      throw new Exception("Must set question before saving comment");
    }
    if (!$this->targetPHID) {
      throw new Exception("Must set target before saving comment");
    }

    $comment  = $this->comment;
    $question = $this->question;
    $target   = $this->targetPHID;
    $comment->save();

    id(new PhabricatorSearchIndexer())
      ->indexDocumentByPHID($question->getPHID());

    // subscribe author and @mentions
    $subeditor = id(new PhabricatorSubscriptionsEditor())
      ->setObject($question)
      ->setActor($actor);

    $subeditor->subscribeExplicit(array($comment->getAuthorPHID()));

    $content = $comment->getContent();
    $at_mention_phids = PhabricatorMarkupEngine::extractPHIDsFromMentions(
      array($content));
    $subeditor->subscribeImplicit($at_mention_phids);
    $subeditor->save();

    if ($this->shouldEmail) {
      // now load subscribers, including implicitly-added @mention victims
      $subscribers = PhabricatorSubscribersQuery
        ::loadSubscribersForPHID($question->getPHID());

      // @mention emails (but not for anyone who has explicitly unsubscribed)
      if (array_intersect($at_mention_phids, $subscribers)) {
        id(new PonderMentionMail(
          $question,
          $comment,
          $actor))
          ->setToPHIDs($at_mention_phids)
          ->send();
      }

      if ($target === $question->getPHID()) {
        $target = $question;
      } else {
        $answers_by_phid = mgroup($question->getAnswers(), 'getPHID');
        $target = head($answers_by_phid[$target]);
      }

      // only send emails to others in the same thread
      $thread = mpull($target->getComments(), 'getAuthorPHID');
      $thread[] = $target->getAuthorPHID();
      $thread[] = $question->getAuthorPHID();

      $other_subs =
        array_diff(
          array_intersect($thread, $subscribers),
          $at_mention_phids);

      // 'Comment' emails for subscribers who are in the same comment thread,
      // including the author of the parent question and/or answer, excluding
      // @mentions (and excluding the author, depending on their MetaMTA
      // settings).
      if ($other_subs) {
        id(new PonderCommentMail(
          $question,
          $comment,
          $actor))
          ->setToPHIDs($other_subs)
          ->send();
      }
    }
  }
}
