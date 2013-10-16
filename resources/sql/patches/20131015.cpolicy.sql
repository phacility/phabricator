ALTER TABLE {$NAMESPACE}_countdown.countdown
  ADD viewPolicy VARCHAR(64) NOT NULL;

UPDATE {$NAMESPACE}_countdown.countdown
  SET viewPolicy = 'users' WHERE viewPolicy = '';
