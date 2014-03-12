<?php

final class PhabricatorCommitTagsField
  extends PhabricatorCommitCustomField {

  public function getFieldKey() {
    return 'diffusion:tags';
  }

  public function shouldAppearInApplicationTransactions() {
    return true;
  }

  public function buildApplicationTransactionMailBody(
    PhabricatorApplicationTransaction $xaction,
    PhabricatorMetaMTAMailBody $body) {

    $params = array(
      'commit' => $this->getObject()->getCommitIdentifier(),
      'callsign' => $this->getObject()->getRepository()->getCallsign(),
    );

    $tags_raw = id(new ConduitCall('diffusion.tagsquery', $params))
      ->setUser($this->getViewer())
      ->execute();

    $tags = DiffusionRepositoryTag::newFromConduit($tags_raw);
    if (!$tags) {
      return;
    }
    $tag_names = mpull($tags, 'getName');
    sort($tag_names);

    $body->addTextSection(pht('TAGS'), implode(', ', $tag_names));
  }

}
