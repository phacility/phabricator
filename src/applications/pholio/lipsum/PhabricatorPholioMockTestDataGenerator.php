<?php

final class PhabricatorPholioMockTestDataGenerator
  extends PhabricatorTestDataGenerator {

  public function getGeneratorName() {
    return pht('Pholio Mocks');
  }

  public function generateObject() {
    $author_phid = $this->loadPhabrictorUserPHID();
    $author = id(new PhabricatorUser())
          ->loadOneWhere('phid = %s', $author_phid);
    $mock = PholioMock::initializeNewMock($author);

    $content_source = $this->getLipsumContentSource();

    $template = id(new PholioTransaction())
      ->setContentSource($content_source);

    // Accumulate Transactions
    $changes = array();
    $changes[PholioTransaction::TYPE_NAME] =
      $this->generateTitle();
    $changes[PholioTransaction::TYPE_DESCRIPTION] =
      $this->generateDescription();
    $changes[PhabricatorTransactions::TYPE_VIEW_POLICY] =
      PhabricatorPolicies::POLICY_PUBLIC;
    $changes[PhabricatorTransactions::TYPE_SUBSCRIBERS] =
      array('=' => $this->getCCPHIDs());

    // Get Files and make Images
    $file_phids = $this->generateImages();
    $files = id(new PhabricatorFileQuery())
      ->setViewer($author)
      ->withPHIDs($file_phids)
      ->execute();
    $mock->setCoverPHID(head($files)->getPHID());
    $sequence = 0;
    $images = array();
    foreach ($files as $file) {
      $image = new PholioImage();
      $image->setFilePHID($file->getPHID());
      $image->setSequence($sequence++);
      $image->attachMock($mock);
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
    $images = newv('PhabricatorFile', array())
      ->loadAllWhere('mimeType = %s', 'image/jpeg');
    $rand_images = array();
    $quantity = rand(2, 10);
    $quantity = min($quantity, count($images));

    if ($quantity) {
      $random_images = $quantity === 1 ?
        array(array_rand($images, $quantity)) :
        array_rand($images, $quantity);

      foreach ($random_images as $random) {
        $rand_images[] = $images[$random]->getPHID();
      }
    }

    // This means you don't have any JPEGs yet. We'll just use a built-in image.
    if (empty($rand_images)) {
      $default = PhabricatorFile::loadBuiltin(
        PhabricatorUser::getOmnipotentUser(),
        'profile.png');
      $rand_images[] = $default->getPHID();
    }
    return $rand_images;
  }

}
