UPDATE {$NAMESPACE}_owners.owners_package
  SET autoReview = 'none' WHERE autoReview = '';
