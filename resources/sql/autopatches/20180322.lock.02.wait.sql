ALTER TABLE {$NAMESPACE}_repository.repository_pushevent
  ADD writeWait BIGINT UNSIGNED;

ALTER TABLE {$NAMESPACE}_repository.repository_pushevent
  ADD readWait BIGINT UNSIGNED;

ALTER TABLE {$NAMESPACE}_repository.repository_pushevent
  ADD hostWait BIGINT UNSIGNED;
