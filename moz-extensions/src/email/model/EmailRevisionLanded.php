<?php


class EmailRevisionLanded implements PublicEmailBody
{
  /** @var EmailRecipient[] */
  public array $subscribers;
  /** @var EmailRecipient[] */
  public array $reviewers;
  public ?EmailRecipient $author;
  public string $revisionHash;
  public ?string $hgLink;
  public ?string $landoLink;

  /**
   * @param EmailRecipient[] $subscribers
   * @param EmailRecipient[] $reviewers
   * @param EmailRecipient|null $author
   * @param string $revisionHash
   * @param string|null $hgLink
   * @param string|null $landoLink
   */
  public function __construct(array $subscribers, array $reviewers, ?EmailRecipient $author, string $revisionHash, ?string $hgLink, ?string $landoLink)
  {
    $this->subscribers = $subscribers;
    $this->reviewers = $reviewers;
    $this->author = $author;
    $this->revisionHash = $revisionHash;
    $this->hgLink = $hgLink;
    $this->landoLink = $landoLink;
  }

  public static function from(ResolveUsers $resolveUsers, ResolveRepositoryDetails $resolveRepositoryDetails, TransactionList $transactions, DifferentialRevision $rawRevision): EmailRevisionLanded {
    $revisionCloseTx = $transactions->getTransactionWithType('differential.revision.close');
    $commit = $resolveRepositoryDetails->resolveCommit($revisionCloseTx->getMetadataValue('commitPHID'));
    $hgLink = $resolveRepositoryDetails->resolveHgLink($commit);

    $landoLink = null;
    $landoUri = PhabricatorEnv::getEnvConfig('lando-ui.url');
    if ($landoUri) {
      $landoLink = (string) id(new PhutilURI($landoUri))
        ->setPath("/D{$rawRevision->getID()}/");
    }

    return new EmailRevisionLanded(
      $resolveUsers->resolveSubscribersAsRecipients(),
      $resolveUsers->resolveReviewersAsRecipients(),
      $resolveUsers->resolveAuthorAsRecipient(),
      $commit->getCommitIdentifier(),
      $hgLink,
      $landoLink
    );
  }


}