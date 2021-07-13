<?php


class EmailRevisionRequestedChanges implements PublicEmailBody {
  public string $transactionLink;
  public ?EmailCommentMessage $mainCommentMessage;
  /** @var EmailInlineComment[] */
  public array $inlineComments;
  /** @var EmailRecipient[] */
  public array $subscribers;
  /** @var EmailRecipient[] */
  public array $reviewers;
  public ?EmailRecipient $author;

  /**
   * @param string $transactionLink
   * @param EmailCommentMessage|null $mainCommentMessage
   * @param EmailInlineComment[] $inlineComments
   * @param EmailRecipient[] $subscribers
   * @param EmailRecipient[] $reviewers
   * @param EmailRecipient|null $author
   */
  public function __construct(string $transactionLink, ?EmailCommentMessage $mainCommentMessage, array $inlineComments, array $subscribers, array $reviewers, ?EmailRecipient $author)
  {
    $this->transactionLink = $transactionLink;
    $this->mainCommentMessage = $mainCommentMessage;
    $this->inlineComments = $inlineComments;
    $this->subscribers = $subscribers;
    $this->reviewers = $reviewers;
    $this->author = $author;
  }

}