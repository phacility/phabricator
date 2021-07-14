<?php


class ResolveComments {
  public TransactionList $transactions;
  public DifferentialRevision $rawRevision;
  public PhabricatorUserStore $userStore;

  public function __construct(TransactionList $transactions, DifferentialRevision $rawRevision, PhabricatorUserStore $userStore) {
    $this->transactions = $transactions;
    $this->rawRevision = $rawRevision;
    $this->userStore = $userStore;
  }

  public function resolvePublicComments(PublicEventPings $pings): PublicRevisionComments {
    $mainCommentMessage = $this->resolveMainComment($pings);
    $inlineComments = $this->resolveInlineComments($pings);
    return new PublicRevisionComments($mainCommentMessage, $inlineComments, $pings);
  }

  public function resolveSecureComments(SecureEventPings $pings): SecureRevisionComments {
    $commentCount = 0;

    $commentTransaction = $this->transactions->attemptGetTransactionWithType('core:comment');
    if ($commentTransaction) {
      $comment = $commentTransaction->getComment()->getContent();
      $commentCount++;
      foreach (self::findPingedUsers($comment) as $user) {
        $pings->add($user);
      }
    }

    $inlineCommentTransactions = $this->transactions->getAllTransactionsWithType('differential:inline');
    foreach ($inlineCommentTransactions as $rawTransaction) {
      $commentCount++;
      foreach (self::findPingedUsers($rawTransaction->getComment()->getContent()) as $user) {
        $pings->add($user);
      }
    }

    return new SecureRevisionComments($commentCount, $pings);
  }

  /**
   * @param PublicEventPings $pings
   * @return EmailCommentMessage|null
   */
  private function resolveMainComment(PublicEventPings $pings): ?EmailCommentMessage {
    $commentTransaction = $this->transactions->attemptGetTransactionWithType('core:comment');
    if (!$commentTransaction) {
      return null;
    } else {
      $rawMessage = $commentTransaction->getComment()->getContent();
      $message = self::renderCommentMarkup($rawMessage);
      foreach (self::findPingedUsers($rawMessage) as $user) {
        $pings->fromMainComment($user, $message);
      }
      return $message;
    }
  }

  /**
   * @return EmailInlineComment[]
   */
  private function resolveInlineComments(PublicEventPings $pings): array {
    $inlineCommentTransactions = $this->transactions->getAllTransactionsWithType('differential:inline');
    $inlineComments = [];
    foreach ($inlineCommentTransactions as $rawTransaction) {
      $comment = $rawTransaction->getComment();

      $changeset = (new DifferentialChangesetQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->withIDs([$comment->getChangesetID()])
        ->needHunks(true)
        ->executeOne();

      $filename = '/' . $changeset->getDisplayFilename();

      $link = '/' . $this->rawRevision->getMonogram()
        . '?id=' . $changeset->getDiffID()
        . '#inline-' . $comment->getID();
      $link = PhabricatorEnv::getProductionURI($link);

      $parentPhid = $comment->getReplyToCommentPHID();
      if ($parentPhid) {
        $contextKind = 'reply';
        $otherComment = (new DifferentialDiffInlineCommentQuery())
          ->setViewer(PhabricatorUser::getOmnipotentUser())
          ->withPHIDs([$comment->getReplyToCommentPHID()])
          ->executeOne();

        $otherDateUtc = new DateTime('@' . $otherComment->getDateCreated(), new DateTimeZone('UTC'));
        $rawOtherAuthor = $this->userStore->find($otherComment->getAuthorPHID());
        $otherMessage = self::renderCommentMarkup($otherComment->getContent());
        $context = new EmailReplyContext($rawOtherAuthor->getUserName(), $otherDateUtc, $otherMessage);
      } else {
        $contextKind = 'code';

        // From getPatch() in DifferentialInlineCommentMailView
        $lineLength = $comment->getLineLength();
        $context = 1; // by default, show one line of context around the select targeted inline code

        // If inline is at least 3 lines long, don't include 1 line of context
        if ($lineLength >= 2) {
          $context = 0;
        }

        // Just show the first few lines of targeted inline code
        if ($lineLength > 6) {
          $lineLength = 6;
        }

        $hunkParser = new DifferentialHunkParser();
        $patch = $hunkParser->makeContextDiff(
          $changeset->getHunks(),
          $comment->getIsNewFile(),
          $comment->getLineNumber(),
          $lineLength,
          $context
        );
        $patch = phutil_split_lines($patch);
        array_shift($patch); // Remove the "@@ -x,y +uv, @@" line

        $oldLineNumber = $newLineNumber = max($comment->getLineNumber() - $context, 0);
        $diffLines = [];
        foreach ($patch as $line) {
          if ($line[0] == '+') {
            $type = 'added';
            $currentLineNumber = $newLineNumber++;
          } else if ($line[0] == '-') {
            $type = 'removed';
            $currentLineNumber = $oldLineNumber++;
          } else {
            $type = 'no-change';
            $oldLineNumber++;
            $currentLineNumber = $newLineNumber++; // Show line number in context of newer version of file
          }

          $diffLines[] = new EmailDiffLine($currentLineNumber, $type, rtrim(substr($line, 1)));
        }


        $context = new EmailCodeContext($diffLines);
      }

      $commentLineNumber = $comment->getLineNumber();
      $rawMessage = $rawTransaction->getComment()->getContent();
      $message = self::renderCommentMarkup($rawMessage);
      $inlineComment = new EmailInlineComment("$filename:$commentLineNumber", $link, $message, $contextKind, $context);
      foreach (self::findPingedUsers($rawMessage) as $user) {
        $pings->fromInlineComment($user, $inlineComment);
      }
      $inlineComments[] = $inlineComment;
    }
    return $inlineComments;
  }

  private function findPingedUsers(string $comment): array
  {
    // According to Phabricator, usernames are constrained such that:
    // > Usernames must contain only numbers, letters, period, underscore, and hyphen, and can not end with a period.
    // > They must have no more than 64 characters.

    $usernameRegex = '/@([A-Za-z0-9\-_\.]{0,63}[a-zA-Z0-9\-_])/';
    if (preg_match_all($usernameRegex, $comment, $matches)) {
      return array_filter(array_map(function ($username) {
        return $this->userStore->findByName($username);
      }, $matches[1]));
    } else {
      return [];
    }
  }

  private static function renderCommentMarkup($markup): EmailCommentMessage
  {
    $asText = self::createMarkupEngine(PhutilRemarkupEngine::MODE_TEXT)->markupText($markup);
    $asHtml = self::createMarkupEngine(PhutilRemarkupEngine::MODE_DEFAULT)->markupText($markup);
    return new EmailCommentMessage($asText, $asHtml);
  }

  private static function createMarkupEngine($mode) {
    return PhabricatorMarkupEngine::newMarkupEngine(array())
      ->setConfig('viewer', PhabricatorUser::getOmnipotentUser())
      ->setConfig('uri.base', PhabricatorEnv::getProductionURI('/'))
      ->setConfig('uri.full', true)
      ->setMode($mode);
  }
}