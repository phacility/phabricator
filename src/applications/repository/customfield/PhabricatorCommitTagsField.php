<?php

final class PhabricatorCommitTagsField
  extends PhabricatorCommitCustomField {

  public function getFieldKey() {
    return 'diffusion:tags';
  }

  public function getFieldName() {
    return pht('Tags');
  }

  public function getFieldDescription() {
    return pht('Shows commit tags in email.');
  }

  public function shouldAppearInTransactionMail() {
    return true;
  }

  public function updateTransactionMailBody(
    PhabricatorMetaMTAMailBody $body,
    PhabricatorApplicationTransactionEditor $editor,
    array $xactions) {

    $params = array(
      'commit' => $this->getObject()->getCommitIdentifier(),
      'repository' => $this->getObject()->getRepository()->getPHID(),
    );

    try {
      $tags_raw = id(new ConduitCall('diffusion.tagsquery', $params))
        ->setUser($this->getViewer())
        ->execute();

      $tags = DiffusionRepositoryTag::newFromConduit($tags_raw);
      if (!$tags) {
        return;
      }
      $tag_names = mpull($tags, 'getName');
      sort($tag_names);
      $tag_names = implode(', ', $tag_names);
    } catch (Exception $ex) {
      $tag_names = pht('<%s: %s>', get_class($ex), $ex->getMessage());
    }

    $body->addTextSection(pht('TAGS'), $tag_names);
  }

}
