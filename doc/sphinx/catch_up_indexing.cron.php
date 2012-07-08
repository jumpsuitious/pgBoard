<?php

//TODO: refactor the two (almost identical) catch_up functions

require_once("config.php");

$CHUNK_SIZE = 5;
$MORE_WHERE = "";//AND date_posted >= '2012-07-07'";

$sphinx = new PDO(SPHINXQL_DSN, SPHINXQL_USER, SPHINXQL_PASSWORD);

function catch_up_threads ()
{
  global $DB, $sphinx, $MORE_WHERE, $CHUNK_SIZE;
  $r = $sphinx->query('SELECT MAX(id),1 AS all FROM thread GROUP BY all')->fetchAll(PDO::FETCH_COLUMN);
  $max_indexed = $r[0];
  if ($max_indexed === NULL) $max_indexed = -1;

  $max_thread = $DB->value('SELECT MAX(id) FROM thread');

  if ($max_indexed >= $max_thread) return;

  $offset = 0;
  while (TRUE)
  {
    $r = $DB->query("SELECT id, subject, member_id, date_posted FROM thread WHERE id > $max_indexed $MORE_WHERE ORDER BY id OFFSET $offset LIMIT $CHUNK_SIZE");
    if ($DB->get_num_rows() == 0) break;
    $offset += $DB->get_num_rows();
    //NOTE: In theory, we could build up a multiple-insert, which might be
    //      more efficient (I swear I read that, but can't seem to find it
    //      again).  For the now, just pop them in one at a time.
    $s = $sphinx->prepare("INSERT INTO thread (id, subject, member_id, date_posted) VALUES (?, ?, ?, ?)");
    while ($data = $DB->load_array())
    {
      print "thread ${data['id']} ${data['date_posted']}: " . substr($data['subject'], 0, 64) . "\n";
      $subject = $data['subject'];
      if (strlen($subject) > MAX_SIZE_TO_INDEX) $subject = substr($subject, 0, MAX_SIZE_TO_INDEX);
      
      $r = $s->execute(array($data['id'], $subject, $data['member_id'], strtotime($data['date_posted'])));
      
      if (!$r)
      {
        $e = $s->errorInfo();
        print "Error indexing thread ${data['id']}: ${e[2]}\n";
      }
    }
  }
}


function catch_up_posts ()
{
  global $DB, $sphinx, $MORE_WHERE, $CHUNK_SIZE;
  $r = $sphinx->query('SELECT MAX(id),1 AS all FROM thread_post GROUP BY all')->fetchAll(PDO::FETCH_COLUMN);
  $max_indexed = $r[0];
  if ($max_indexed === NULL) $max_indexed = -1;

  $max_thread = $DB->value('SELECT MAX(id) FROM thread_post');
  if ($max_indexed >= $max_thread) return;

  $offset = 0;
  while (TRUE)
  {
    $r = $DB->query("SELECT id, body, member_id, thread_id, date_posted FROM thread_post WHERE id > $max_indexed $MORE_WHERE ORDER BY id OFFSET $offset LIMIT $CHUNK_SIZE");
    if ($DB->get_num_rows() == 0) break;
    $offset += $DB->get_num_rows();
    //NOTE: In theory, we could build up a multiple-insert, which might be
    //      more efficient (I swear I read that, but can't seem to find it
    //      again).  For the now, just pop them in one at a time.
    $s = $sphinx->prepare("INSERT INTO thread_post (id, body, member_id, thread_id, date_posted) VALUES (?, ?, ?, ?, ?)");
    while ($data = $DB->load_array())
    {      
      $subject = $data['body'];
      if (strlen($subject) > MAX_SIZE_TO_INDEX) $subject = substr($subject, 0, MAX_SIZE_TO_INDEX);
      print "post ${data['id']} ${data['date_posted']}: " . str_replace("\n", "|", substr($subject, 0, 64)) . "\n";

      $r = $s->execute(array($data['id'], $subject, $data['member_id'], $data['thread_id'], strtotime($data['date_posted'])));
      
      if (!$r)
      {
        $e = $s->errorInfo();
        print "Error indexing post ${data['id']}: ${e[2]}\n";
      }
    }
  }
}

catch_up_threads();
catch_up_posts();

//print "\nDone.\n";

?>
