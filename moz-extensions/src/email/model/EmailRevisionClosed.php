<?php


class EmailRevisionClosed implements PublicEmailBody
{
  public ?EmailCommentMessage $mainCommentMessage;
  /** @var EmailInlineComment[] */
  public array $inlineComments;
  public string $transactionLink;
  /** @var EmailRecipient[] */
  public array $subscribers;
  /** @var EmailRecipient[] */
  public array $reviewers;
  public ?EmailRecipient $author;

  /**
   * @param EmailCommentMessage|null $mainCommentMessage
   * @param EmailInlineComment[] $inlineComments
   * @param string $transactionLink
   * @param EmailRecipient[] $subscribers
   * @param EmailRecipient[] $reviewers
   * @param EmailRecipient|null $author
   */
  public function __construct(?EmailCommentMessage $mainCommentMessage, array $inlineComments, string $transactionLink, array $subscribers, array $reviewers, ?EmailRecipient $author)
  {
    $this->mainCommentMessage = $mainCommentMessage;
    $this->inlineComments = $inlineComments;
    $this->transactionLink = $transactionLink;
    $this->subscribers = $subscribers;
    $this->reviewers = $reviewers;
    $this->author = $author;
  }


}