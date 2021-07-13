<?php


class PublicRevisionComments {
  public ?EmailCommentMessage $mainCommentMessage;
  /** @var EmailInlineComment[] */
  public array $inlineComments;
  public PublicEventPings $pings;

  /**
   * @param EmailCommentMessage|null $mainCommentMessage
   * @param EmailInlineComment[] $inlineComments
   * @param PublicEventPings $pings
   */
  public function __construct(?EmailCommentMessage $mainCommentMessage, array $inlineComments, PublicEventPings $pings) {
    $this->mainCommentMessage = $mainCommentMessage;
    $this->inlineComments = $inlineComments;
    $this->pings = $pings;
  }


}