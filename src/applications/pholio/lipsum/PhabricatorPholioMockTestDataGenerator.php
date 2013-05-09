<?php

final class PhabricatorPholioMockTestDataGenerator
  extends PhabricatorTestDataGenerator {

  public function generate() {
    $authorPHID = $this->loadPhabrictorUserPHID();
    $author = id(new PhabricatorUser())
          ->loadOneWhere('phid = %s', $authorPHID);
    $mock = id(new PholioMock())
      ->setAuthorPHID($authorPHID);
    $content_source = PhabricatorContentSource::newForSource(
      PhabricatorContentSource::SOURCE_UNKNOWN,
      array());
    $template = id(new PholioTransaction())
      ->setContentSource($content_source);

    // Accumulate Transactions
    $changes = array();
    $changes[PholioTransactionType::TYPE_NAME] =
      $this->generateTitle();
    $changes[PholioTransactionType::TYPE_DESCRIPTION] =
      $this->generateDescription();
    $changes[PhabricatorTransactions::TYPE_VIEW_POLICY] =
      PhabricatorPolicies::POLICY_PUBLIC;
    $changes[PhabricatorTransactions::TYPE_SUBSCRIBERS] =
      array('=' => $this->getCCPHIDs());

    // Get Files and make Images
    $filePHIDS = $this->generateImages();
    $files = id(new PhabricatorFileQuery())
      ->setViewer($author)
      ->withPHIDs($filePHIDS)
      ->execute();
    $mock->setCoverPHID(head($files)->getPHID());
    $sequence = 0;
    $images = array();
    foreach ($files as $file) {
      $image = new PholioImage();
      $image->setFilePHID($file->getPHID());
      $image->setSequence($sequence++);
      $images[] = $image;
    }

    // Apply Transactions
    $transactions = array();
    foreach ($changes as $type => $value) {
      $transaction = clone $template;
      $transaction->setTransactionType($type);
      $transaction->setNewValue($value);
      $transactions[] = $transaction;
    }
    $mock->openTransaction();
    $editor = id(new PholioMockEditor())
      ->setContentSource($content_source)
      ->setContinueOnNoEffect(true)
      ->setActor($author)
      ->applyTransactions($mock, $transactions);
    foreach ($images as $image) {
      $image->setMockID($mock->getID());
      $image->save();
    }

    $mock->saveTransaction();
    return $mock->save();
  }

  public function generateTitle() {
    return id(new PhutilLipsumContextFreeGrammar())
      ->generate();
  }

  public function generateDescription() {
    return id(new PhutilLipsumContextFreeGrammar())
      ->generateSeveral(rand(30, 40));
  }

  public function getCCPHIDs() {
    $ccs = array();
    for ($i = 0; $i < rand(1, 4);$i++) {
      $ccs[] = $this->loadPhabrictorUserPHID();
    }
    return $ccs;
  }

  public function generateImages() {
    $images = newv("PhabricatorFile", array())
      ->loadAllWhere("mimeType = %s", "image/jpeg");
    $rand_images = array();
    $quantity = rand(2, 10);
    foreach (array_rand($images, $quantity) as $random) {
      $rand_images[] = $images[$random]->getPHID();
    }
    return $rand_images;
  }


}
