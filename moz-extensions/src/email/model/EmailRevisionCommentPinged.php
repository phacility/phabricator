<?php


class EmailRevisionCommentPinged implements PublicEmailBody {
  public EmailRecipient $recipient;
  public string $transactionLink;
  public ?EmailCommentMessage $pingedMainCommentMessage;
  /** @var EmailInlineComment[] */
  public array $pingedInlineComments;

  /**
   * @param EmailRecipient $recipient
   * @param string $transactionLink
   * @param EmailCommentMessage|null $pingedMainCommentMessage
   * @param EmailInlineComment[] $pingedInlineComments
   */
  public function __construct(EmailRecipient $recipient, string $transactionLink, ?EmailCommentMessage $pingedMainCommentMessage, array $pingedInlineComments) {
    $this->recipient = $recipient;
    $this->transactionLink = $transactionLink;
    $this->pingedMainCommentMessage = $pingedMainCommentMessage;
    $this->pingedInlineComments = $pingedInlineComments;
  }


}