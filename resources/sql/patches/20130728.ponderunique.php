<?php

$map = array();

echo pht('Merging duplicate answers by authors...')."\n";

$atable = new PonderAnswer();
$conn_w = $atable->establishConnection('w');
$conn_w->openTransaction();

$answers = new LiskMigrationIterator(new PonderAnswer());
foreach ($answers as $answer) {
  $aid = $answer->getID();
  $qid = $answer->getQuestionID();
  $author_phid = $answer->getAuthorPHID();

  echo pht('Processing answer ID #%d...', $aid)."\n";

  if (empty($map[$qid][$author_phid])) {
    echo pht('Answer is unique.')."\n";
    $map[$qid][$author_phid] = $answer;
    continue;
  } else {
    echo pht('Merging answer.')."\n";
    $target = $map[$qid][$author_phid];
    queryfx(
      $conn_w,
      'UPDATE %T SET content = %s WHERE id = %d',
      $target->getTableName(),

      $target->getContent().
      "\n\n".
      "---".
      "\n\n".
      "> (This content was automatically merged from another answer by the ".
      "same author.)".
      "\n\n".
      $answer->getContent(),

      $target->getID());

    queryfx(
      $conn_w,
      'DELETE FROM %T WHERE id = %d',
      $target->getTableName(),
      $answer->getID());

    queryfx(
      $conn_w,
      'UPDATE %T SET targetPHID = %s WHERE targetPHID = %s',
      'ponder_comment',
      $target->getPHID(),
      $answer->getPHID());
  }
}

$conn_w->saveTransaction();
echo pht('Done.')."\n";
