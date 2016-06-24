UPDATE {$NAMESPACE}_phame.phame_blogtransaction
  SET transactionType = 'phame.blog.full.domain'
  WHERE transactionType = 'phame.blog.domain';
