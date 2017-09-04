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
    $ngram_engine = id(new PhabricatorNgramEngine());

    // Copy all of the "title" and "body" fields to create new "core" fields.
    // This allows users to search "in title or body" with the "core:" prefix.
    $document_fields = $document->getFieldData();
    $virtual_fields = array();
    foreach ($document_fields as $field) {
      $virtual_fields[] = $field;

      list($key, $raw_corpus) = $field;
      switch ($key) {
        case PhabricatorSearchDocumentFieldType::FIELD_TITLE:
        case PhabricatorSearchDocumentFieldType::FIELD_BODY:
          $virtual_fields[] = array(
            PhabricatorSearchDocumentFieldType::FIELD_CORE,
            $raw_corpus,
          );
          break;
      }
    }

    $key_all = PhabricatorSearchDocumentFieldType::FIELD_ALL;

    $empty_template = array(
      'raw' => array(),
      'term' => array(),
      'normal' => array(),
    );

    $ferret_corpus_map = array(
      $key_all => $empty_template,
    );

    foreach ($virtual_fields as $field) {
      list($key, $raw_corpus) = $field;
      if (!strlen($raw_corpus)) {
        continue;
      }

      $term_corpus = $ngram_engine->newTermsCorpus($raw_corpus);

      $normal_corpus = $stemmer->stemCorpus($raw_corpus);
      $normal_coprus = $ngram_engine->newTermsCorpus($normal_corpus);

      if (!isset($ferret_corpus_map[$key])) {
        $ferret_corpus_map[$key] = $empty_template;
      }

      $ferret_corpus_map[$key]['raw'][] = $raw_corpus;
      $ferret_corpus_map[$key]['term'][] = $term_corpus;
      $ferret_corpus_map[$key]['normal'][] = $normal_corpus;

      $ferret_corpus_map[$key_all]['raw'][] = $raw_corpus;
      $ferret_corpus_map[$key_all]['term'][] = $term_corpus;
      $ferret_corpus_map[$key_all]['normal'][] = $normal_corpus;
    }

    $ferret_fields = array();
    $ngrams_source = array();
    foreach ($ferret_corpus_map as $key => $fields) {
      $raw_corpus = $fields['raw'];
      $raw_corpus = implode("\n", $raw_corpus);
      $ngrams_source[] = $raw_corpus;

      $normal_corpus = $fields['normal'];
      $normal_corpus = implode(' ', $normal_corpus);
      if (strlen($normal_corpus)) {
        $ngrams_source[] = $normal_corpus;
        $normal_corpus = ' '.$normal_corpus.' ';
      }

      $term_corpus = $fields['term'];
      $term_corpus = implode(' ', $term_corpus);
      if (strlen($term_corpus)) {
        $ngrams_source[] = $term_corpus;
        $term_corpus = ' '.$term_corpus.' ';
      }

      $ferret_fields[] = $engine->newFieldObject()
        ->setFieldKey($key)
        ->setRawCorpus($raw_corpus)
        ->setTermCorpus($term_corpus)
        ->setNormalCorpus($normal_corpus);
    }
    $ngrams_source = implode(' ', $ngrams_source);

    $ngrams = $ngram_engine->getNgramsFromString($ngrams_source, 'index');

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
