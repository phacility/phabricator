UPDATE {$NAMESPACE}_differential.differential_revision
  SET status = "needs-review" WHERE status = "0";

UPDATE {$NAMESPACE}_differential.differential_revision
  SET status = "needs-revision" WHERE status = "1";

UPDATE {$NAMESPACE}_differential.differential_revision
  SET status = "accepted" WHERE status = "2";

UPDATE {$NAMESPACE}_differential.differential_revision
  SET status = "published" WHERE status = "3";

UPDATE {$NAMESPACE}_differential.differential_revision
  SET status = "abandoned" WHERE status = "4";

UPDATE {$NAMESPACE}_differential.differential_revision
  SET status = "changes-planned" WHERE status = "5";
