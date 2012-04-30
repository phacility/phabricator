ALTER TABLE `{$NAMESPACE}_user`.`user_log`
  -- 45 is length of "0000:0000:0000:0000:0000:0000:255.255.255.255".
  MODIFY `remoteAddr` varchar(45) COLLATE 'utf8_general_ci' NOT NULL;
