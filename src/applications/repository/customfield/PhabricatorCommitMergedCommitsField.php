<?php

final class PhabricatorCommitMergedCommitsField
  extends PhabricatorCommitCustomField {

  public function getFieldKey() {
    return 'diffusion:mergedcommits';
  }

  public function getFieldName() {
    return pht('Merged Commits');
  }

  public function getFieldDescription() {
    return pht('For merge commits, shows merged changes in email.');
  }

  public function shouldDisableByDefault() {
    return true;
  }

  public function shouldAppearInTransactionMail() {
    return true;
  }

  public function updateTransactionMailBody(
    PhabricatorMetaMTAMailBody $body,
    PhabricatorApplicationTransactionEditor $editor,
    array $xactions) {

    // Put all the merged commits info int the mail body if this is a merge
    $merges_caption = '';
    // TODO: Make this limit configurable after T6030
    $limit = 50;
    $commit = $this->getObject();

    try {
      $merges = DiffusionPathChange::newFromConduit(
        id(new ConduitCall('diffusion.mergedcommitsquery', array(
          'commit' => $commit->getCommitIdentifier(),
          'limit' => $limit + 1,
          'repository' => $commit->getRepository()->getPHID(),
        )))
        ->setUser($this->getViewer())
        ->execute());

      if (count($merges) > $limit) {
        $merges = array_slice($merges, 0, $limit);
        $merges_caption =
          pht("This commit merges more than %d changes. Only the first ".
          "%d are shown.\n", $limit, $limit);
      }

      if ($merges) {
        $merge_commits = array();
        foreach ($merges as $merge) {
          $merge_commits[] = $merge->getAuthorName().
            ': '.
            $merge->getSummary();
        }
        $body->addTextSection(
          pht('MERGED COMMITS'),
          $merges_caption.implode("\n", $merge_commits));
      }
    } catch (ConduitException $ex) {
      // Log the exception into the email body
      $body->addTextSection(
        pht('MERGED COMMITS'),
        pht('Error generating merged commits: ').$ex->getMessage());
    }

  }

}
