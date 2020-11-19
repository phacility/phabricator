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

    $is_closed = 0;
    $author_phid = null;
    $owner_phid = null;
    foreach ($document->getRelationshipData() as $relationship) {
      list($related_type, $related_phid) = $relationship;
      switch ($related_type) {
        case PhabricatorSearchRelationship::RELATIONSHIP_OPEN:
          $is_closed = 0;
          break;
        case PhabricatorSearchRelationship::RELATIONSHIP_CLOSED:
          $is_closed = 1;
          break;
        case PhabricatorSearchRelationship::RELATIONSHIP_OWNER:
          $owner_phid = $related_phid;
          break;
        case PhabricatorSearchRelationship::RELATIONSHIP_UNOWNED:
          $owner_phid = null;
          break;
        case PhabricatorSearchRelationship::RELATIONSHIP_AUTHOR:
          $author_phid = $related_phid;
          break;
      }
    }

    $stemmer = $engine->newStemmer();

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

      $virtual_fields[] = array(
        PhabricatorSearchDocumentFieldType::FIELD_ALL,
        $raw_corpus,
      );
    }

    $empty_template = array(
      'raw' => array(),
      'term' => array(),
      'normal' => array(),
    );

    $ferret_corpus_map = array();

    foreach ($virtual_fields as $field) {
      list($key, $raw_corpus) = $field;
      if (!strlen($raw_corpus)) {
        continue;
      }

      $term_corpus = $engine->newTermsCorpus($raw_corpus);

      $normal_corpus = $stemmer->stemCorpus($raw_corpus);
      $normal_corpus = $engine->newTermsCorpus($normal_corpus);

      if (!isset($ferret_corpus_map[$key])) {
        $ferret_corpus_map[$key] = $empty_template;
      }

      $ferret_corpus_map[$key]['raw'][] = $raw_corpus;
      $ferret_corpus_map[$key]['term'][] = $term_corpus;
      $ferret_corpus_map[$key]['normal'][] = $normal_corpus;
    }

    $ferret_fields = array();
    $ngrams_source = array();
    foreach ($ferret_corpus_map as $key => $fields) {
      $raw_corpus = $fields['raw'];
      $raw_corpus = implode("\n", $raw_corpus);
      if (strlen($raw_corpus)) {
        $ngrams_source[] = $raw_corpus;
      }

      $normal_corpus = $fields['normal'];
      $normal_corpus = implode("\n", $normal_corpus);
      if (strlen($normal_corpus)) {
        $ngrams_source[] = $normal_corpus;
      }

      $term_corpus = $fields['term'];
      $term_corpus = implode("\n", $term_corpus);
      if (strlen($term_corpus)) {
        $ngrams_source[] = $term_corpus;
      }

      $ferret_fields[] = array(
        'fieldKey' => $key,
        'rawCorpus' => $raw_corpus,
        'termCorpus' => $term_corpus,
        'normalCorpus' => $normal_corpus,
      );
    }
    $ngrams_source = implode("\n", $ngrams_source);

    $ngram_engine = new PhabricatorSearchNgramEngine();
    $ngrams = $ngram_engine->getTermNgramsFromString($ngrams_source);

    $conn = $object->establishConnection('w');

    if ($ngrams) {
      $common = queryfx_all(
        $conn,
        'SELECT ngram FROM %T WHERE ngram IN (%Ls)',
        $engine->getCommonNgramsTableName(),
        $ngrams);
      $common = ipull($common, 'ngram', 'ngram');

      foreach ($ngrams as $key => $ngram) {
        if (isset($common[$ngram])) {
          unset($ngrams[$key]);
          continue;
        }

        // NOTE: MySQL discards trailing whitespace in CHAR(X) columns.
        $trimmed_ngram = rtrim($ngram, ' ');
        if (isset($common[$trimmed_ngram])) {
          unset($ngrams[$key]);
          continue;
        }
      }
    }

    $object->openTransaction();

    try {
      // See T13587. If this document already exists in the index, we try to
      // update the existing rows to avoid leaving the ngrams table heavily
      // fragmented.

      $old_document = queryfx_one(
        $conn,
        'SELECT id FROM %T WHERE objectPHID = %s',
        $engine->getDocumentTableName(),
        $object->getPHID());
      if ($old_document) {
        $old_document_id = (int)$old_document['id'];
      } else {
        $old_document_id = null;
      }

      if ($old_document_id === null) {
        queryfx(
          $conn,
          'INSERT INTO %T (objectPHID, isClosed, epochCreated, epochModified,
            authorPHID, ownerPHID) VALUES (%s, %d, %d, %d, %ns, %ns)',
          $engine->getDocumentTableName(),
          $object->getPHID(),
          $is_closed,
          $document->getDocumentCreated(),
          $document->getDocumentModified(),
          $author_phid,
          $owner_phid);
        $document_id = $conn->getInsertID();

        $is_new = true;
      } else {
        $document_id = $old_document_id;
        queryfx(
          $conn,
          'UPDATE %T
            SET
              isClosed = %d,
              epochCreated = %d,
              epochModified = %d,
              authorPHID = %ns,
              ownerPHID = %ns
            WHERE id = %d',
          $engine->getDocumentTableName(),
          $is_closed,
          $document->getDocumentCreated(),
          $document->getDocumentModified(),
          $author_phid,
          $owner_phid,
          $document_id);

        $is_new = false;
      }

      $this->updateStoredFields(
        $conn,
        $is_new,
        $document_id,
        $engine,
        $ferret_fields);

      $this->updateStoredNgrams(
        $conn,
        $is_new,
        $document_id,
        $engine,
        $ngrams);

    } catch (Exception $ex) {
      $object->killTransaction();
      throw $ex;
    } catch (Throwable $ex) {
      $object->killTransaction();
      throw $ex;
    }

    $object->saveTransaction();
  }

  private function updateStoredFields(
    AphrontDatabaseConnection $conn,
    $is_new,
    $document_id,
    PhabricatorFerretEngine $engine,
    $new_fields) {

    if (!$is_new) {
      $old_fields = queryfx_all(
        $conn,
        'SELECT * FROM %T WHERE documentID = %d',
        $engine->getFieldTableName(),
        $document_id);
    } else {
      $old_fields = array();
    }

    $old_fields = ipull($old_fields, null, 'fieldKey');
    $new_fields = ipull($new_fields, null, 'fieldKey');

    $delete_rows = array();
    $insert_rows = array();
    $update_rows = array();

    foreach ($old_fields as $field_key => $old_field) {
      if (!isset($new_fields[$field_key])) {
        $delete_rows[] = $old_field;
      }
    }

    $compare_keys = array(
      'rawCorpus',
      'termCorpus',
      'normalCorpus',
    );

    foreach ($new_fields as $field_key => $new_field) {
      if (!isset($old_fields[$field_key])) {
        $insert_rows[] = $new_field;
        continue;
      }

      $old_field = $old_fields[$field_key];

      $same_row = true;
      foreach ($compare_keys as $compare_key) {
        if ($old_field[$compare_key] !== $new_field[$compare_key]) {
          $same_row = false;
          break;
        }
      }

      if ($same_row) {
        continue;
      }

      $new_field['id'] = $old_field['id'];
      $update_rows[] = $new_field;
    }

    if ($delete_rows) {
      queryfx(
        $conn,
        'DELETE FROM %T WHERE id IN (%Ld)',
        $engine->getFieldTableName(),
        ipull($delete_rows, 'id'));
    }

    foreach ($update_rows as $update_row) {
      queryfx(
        $conn,
        'UPDATE %T
          SET
            rawCorpus = %s,
            termCorpus = %s,
            normalCorpus = %s
          WHERE id = %d',
        $engine->getFieldTableName(),
        $update_row['rawCorpus'],
        $update_row['termCorpus'],
        $update_row['normalCorpus'],
        $update_row['id']);
    }

    foreach ($insert_rows as $insert_row) {
      queryfx(
        $conn,
        'INSERT INTO %T (documentID, fieldKey, rawCorpus, termCorpus,
          normalCorpus) VALUES (%d, %s, %s, %s, %s)',
          $engine->getFieldTableName(),
          $document_id,
          $insert_row['fieldKey'],
          $insert_row['rawCorpus'],
          $insert_row['termCorpus'],
          $insert_row['normalCorpus']);
    }
  }

  private function updateStoredNgrams(
    AphrontDatabaseConnection $conn,
    $is_new,
    $document_id,
    PhabricatorFerretEngine $engine,
    $new_ngrams) {

    if ($is_new) {
      $old_ngrams = array();
    } else {
      $old_ngrams = queryfx_all(
        $conn,
        'SELECT id, ngram FROM %T WHERE documentID = %d',
        $engine->getNgramsTableName(),
        $document_id);
    }

    $old_ngrams = ipull($old_ngrams, 'id', 'ngram');
    $new_ngrams = array_fuse($new_ngrams);

    $delete_ids = array();
    $insert_ngrams = array();

    // NOTE: MySQL discards trailing whitespace in CHAR(X) columns.

    foreach ($old_ngrams as $ngram => $id) {
      if (isset($new_ngrams[$ngram])) {
        continue;
      }

      $untrimmed_ngram = $ngram.' ';
      if (isset($new_ngrams[$untrimmed_ngram])) {
        continue;
      }

      $delete_ids[] = $id;
    }

    foreach ($new_ngrams as $ngram) {
      if (isset($old_ngrams[$ngram])) {
        continue;
      }

      $trimmed_ngram = rtrim($ngram, ' ');
      if (isset($old_ngrams[$trimmed_ngram])) {
        continue;
      }

      $insert_ngrams[] = $ngram;
    }

    if ($delete_ids) {
      $sql = array();
      foreach ($delete_ids as $id) {
        $sql[] = qsprintf(
          $conn,
          '%d',
          $id);
      }

      foreach (PhabricatorLiskDAO::chunkSQL($sql) as $chunk) {
        queryfx(
          $conn,
          'DELETE FROM %T WHERE id IN (%LQ)',
          $engine->getNgramsTableName(),
          $chunk);
      }
    }

    if ($insert_ngrams) {
      $sql = array();
      foreach ($insert_ngrams as $ngram) {
        $sql[] = qsprintf(
          $conn,
          '(%d, %s)',
          $document_id,
          $ngram);
      }

      foreach (PhabricatorLiskDAO::chunkSQL($sql) as $chunk) {
        queryfx(
          $conn,
          'INSERT INTO %T (documentID, ngram) VALUES %LQ',
          $engine->getNgramsTableName(),
          $chunk);
      }
    }
  }

  public function newFerretSearchFunctions() {
    return array(
      id(new FerretConfigurableSearchFunction())
        ->setFerretFunctionName('all')
        ->setFerretFieldKey(PhabricatorSearchDocumentFieldType::FIELD_ALL),
      id(new FerretConfigurableSearchFunction())
        ->setFerretFunctionName('title')
        ->setFerretFieldKey(PhabricatorSearchDocumentFieldType::FIELD_TITLE),
      id(new FerretConfigurableSearchFunction())
        ->setFerretFunctionName('body')
        ->setFerretFieldKey(PhabricatorSearchDocumentFieldType::FIELD_BODY),
      id(new FerretConfigurableSearchFunction())
        ->setFerretFunctionName('core')
        ->setFerretFieldKey(PhabricatorSearchDocumentFieldType::FIELD_CORE),
      id(new FerretConfigurableSearchFunction())
        ->setFerretFunctionName('comment')
        ->setFerretFieldKey(PhabricatorSearchDocumentFieldType::FIELD_COMMENT),
    );
  }

}
