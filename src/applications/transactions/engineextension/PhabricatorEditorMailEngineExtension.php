<?php

final class PhabricatorEditorMailEngineExtension
  extends PhabricatorMailEngineExtension {

  const EXTENSIONKEY = 'editor';

  public function supportsObject($object) {
    return true;
  }

  public function newMailStampTemplates($object) {
    $templates = array();

    $templates[] = id(new PhabricatorPHIDMailStamp())
      ->setKey('actor')
      ->setLabel(pht('Acting User'));

    $templates[] = id(new PhabricatorStringMailStamp())
      ->setKey('via')
      ->setLabel(pht('Via Content Source'));

    $templates[] = id(new PhabricatorBoolMailStamp())
      ->setKey('silent')
      ->setLabel(pht('Silent Edit'));

    $templates[] = id(new PhabricatorBoolMailStamp())
      ->setKey('encrypted')
      ->setLabel(pht('Encryption Required'));

    $templates[] = id(new PhabricatorBoolMailStamp())
      ->setKey('new')
      ->setLabel(pht('New Object'));

    $templates[] = id(new PhabricatorPHIDMailStamp())
      ->setKey('mention')
      ->setLabel(pht('Mentioned User'));

    $templates[] = id(new PhabricatorStringMailStamp())
      ->setKey('herald')
      ->setLabel(pht('Herald Rule'));

    $templates[] = id(new PhabricatorPHIDMailStamp())
      ->setKey('removed')
      ->setLabel(pht('Recipient Removed'));

    return $templates;
  }

  public function newMailStamps($object, array $xactions) {
    $editor = $this->getEditor();
    $viewer = $this->getViewer();

    $this->getMailStamp('actor')
      ->setValue($editor->getActingAsPHID());

    $content_source = $editor->getContentSource();
    $this->getMailStamp('via')
      ->setValue($content_source->getSourceTypeConstant());

    $this->getMailStamp('silent')
      ->setValue($editor->getIsSilent());

    $this->getMailStamp('encrypted')
      ->setValue($editor->getMustEncrypt());

    $this->getMailStamp('new')
      ->setValue($editor->getIsNewObject());

    $mentioned_phids = $editor->getMentionedPHIDs();
    $this->getMailStamp('mention')
      ->setValue($mentioned_phids);

    $this->getMailStamp('herald')
      ->setValue($editor->getHeraldRuleMonograms());

    $this->getMailStamp('removed')
      ->setValue($editor->getRemovedRecipientPHIDs());
  }

}
