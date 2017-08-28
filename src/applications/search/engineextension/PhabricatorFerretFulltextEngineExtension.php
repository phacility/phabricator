<?php

final class PhabricatorFerretFulltextEngineExtension
  extends PhabricatorFulltextEngineExtension {

  const EXTENSIONKEY = 'ferret';


  public function getExtensionName() {
    return pht('Ferret Fulltext Engine');
  }


  public function shouldIndexFulltextObject($object) {
    return ($object instanceof PhabricatorFerretInterface);
  }


  public function indexFulltextObject(
    $object,
    PhabricatorSearchAbstractDocument $document) {

    $phid = $document->getPHID();
    $engine = $object->newFerretEngine();

    $ferret_document = $engine->newDocumentObject()
      ->setObjectPHID($phid)
      ->setIsClosed(0)
      ->setEpochCreated(0)
      ->setEpochModified(0);

    $stemmer = new PhutilSearchStemmer();

    $ferret_fields = array();
    $ngrams_source = array();
    foreach ($document->getFieldData() as $field) {
      list($key, $raw_corpus) = $field;

      if (!strlen($raw_corpus)) {
        continue;
      }

      $normal_corpus = $stemmer->stemCorpus($raw_corpus);

      $ferret_fields[] = $engine->newFieldObject()
        ->setFieldKey($key)
        ->setRawCorpus($raw_corpus)
        ->setNormalCorpus($normal_corpus);

      $ngrams_source[] = $raw_corpus;
    }
    $ngrams_source = implode(' ', $ngrams_source);

    $ngrams = id(new PhabricatorNgramEngine())
      ->getNgramsFromString($ngrams_source, 'index');

    $ferret_document->openTransaction();

    try {
      $this->deleteOldDocument($engine, $object, $document);

      $ferret_document->save();

      $document_id = $ferret_document->getID();
      foreach ($ferret_fields as $ferret_field) {
        $ferret_field
          ->setDocumentID($document_id)
          ->save();
      }

      $ferret_ngrams = $engine->newNgramsObject();
      $conn = $ferret_ngrams->establishConnection('w');

      $sql = array();
      foreach ($ngrams as $ngram) {
        $sql[] = qsprintf(
          $conn,
          '(%d, %s)',
          $document_id,
          $ngram);
      }

      foreach (PhabricatorLiskDAO::chunkSQL($sql) as $chunk) {
        queryfx(
          $conn,
          'INSERT INTO %T (documentID, ngram) VALUES %Q',
          $ferret_ngrams->getTableName(),
          $chunk);
      }
    } catch (Exception $ex) {
      $ferret_document->killTransaction();
      throw $ex;
    }

    $ferret_document->saveTransaction();
  }


  private function deleteOldDocument(
    PhabricatorFerretEngine $engine,
    $object,
    PhabricatorSearchAbstractDocument $document) {

    $old_document = $engine->newDocumentObject()->loadOneWhere(
      'objectPHID = %s',
      $document->getPHID());
    if (!$old_document) {
      return;
    }

    $conn = $old_document->establishConnection('w');
    $old_id = $old_document->getID();

    queryfx(
      $conn,
      'DELETE FROM %T WHERE id = %d',
      $engine->newDocumentObject()->getTableName(),
      $old_id);

    queryfx(
      $conn,
      'DELETE FROM %T WHERE documentID = %d',
      $engine->newFieldObject()->getTableName(),
      $old_id);

    queryfx(
      $conn,
      'DELETE FROM %T WHERE documentID = %d',
      $engine->newNgramsObject()->getTableName(),
      $old_id);
  }

}
