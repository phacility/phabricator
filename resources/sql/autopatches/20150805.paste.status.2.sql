UPDATE {$NAMESPACE}_pastebin.pastebin_paste
  SET status = 'active' WHERE status = '';
