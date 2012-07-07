<?php
class Search
{
  function _exec ($statement, $args, $msg = "DB action failed")
  {
    $r = $statement->execute($args);
    if (!$r)
    {
      $e = $statement->errorInfo();
      $msg = $msg . ": " . $e[2]; // Might leak info
      trigger_error($msg);
      //throw new RuntimeException($msg);
    }
    return $r;
  }

  function _connect ($is_query = FALSE)
  {
    if (!SPHINXQL_DSN) return FALSE;

    if (!$is_query)
    {
      // Queries are always okay, but if we're indexing, we might want to
      // abort here.
      if  (!ENABLE_RT_INDEXING) return FALSE;
    }

    try
    {
      return new PDO(SPHINXQL_DSN, SPHINXQL_USER, SPHINXQL_PASSWORD);
    }
    catch (Exception $e)
    {
      trigger_error("Sphinx connection exception: " . $e->getMessage());
      return FALSE;
    }
  }

  /* Update post $id's body to be $body */
  function _update_post($sphinx, $id, $body)
  {
    if (strlen($body) > MAX_SIZE_TO_INDEX) $body = substr($body, 0, MAX_SIZE_TO_INDEX);

    // UPDATE doesn't work, so we load the post and then replace the body.
    $id = intval($id);
    $s = $sphinx->prepare("SELECT member_id, thread_id, date_posted FROM thread_post WHERE id=" . $id);
    if (!$this->_exec($s, array(), "Failed to load post index")) return FALSE;
    $post = $s->fetch(PDO::FETCH_ASSOC);
    $post['id'] = $id;
    $post['body'] = $body;

    $s = $sphinx->prepare("REPLACE INTO thread_post (id, body, member_id, thread_id, date_posted) VALUES (:id, :body, :member_id, :thread_id, :date_posted)");
    //print_r($post);
    if (!$this->_exec($s, $post, "Failed to update post index")) return FALSE;

    return TRUE;
  }


  function query($query,$index,$offset=0)
  {
    $empty = array("matches"=>array(), "total"=>0);

    $sphinx = $this->_connect(TRUE);
    if (!$sphinx) return $empty;

    if ($index != "thread" && $index != "thread_post") return $empty;
    $offset = intval($offset);

    $s = $sphinx->prepare("SELECT COUNT(*) FROM $index WHERE MATCH(?) GROUP BY member_id");
    if (!$this->_exec($s,array($query), "Failed to query index index")) return $empty;
    $total = $s->fetchColumn();

    $s = $sphinx->prepare("SELECT id FROM $index WHERE MATCH(?) ORDER BY date_posted DESC LIMIT $offset,100");
    if (!$this->_exec($s,array($query), "Failed to query index index")) return $empty;
    $r = array("matches"=>$s->fetchAll(PDO::FETCH_COLUMN, 0), "total"=>$total);

    return $r;
  }

  function insert($type,$doc) { return TRUE; }
  function delete($type,$id) { return TRUE; }

  function thread_insert($data,$id)
  {
    $sphinx = $this->_connect();
    if (!$sphinx) return TRUE;

    $subject = $data['subject'];
    if (strlen($subject) > MAX_SIZE_TO_INDEX) $subject = substr($subject, 0, MAX_SIZE_TO_INDEX);

    $s = $sphinx->prepare("INSERT INTO thread (id, subject, member_id, date_posted) VALUES (?, ?, ?, ?)");
    $q = array($id, $subject, $data['member_id'], strtotime($data['date_posted']));
    // Using strtotime() is a bit of a hack.  What we really want to do is
    // get UNIX_TIMESTAMP(CURRENT_TIMESTAMP) from postgres, but that's also
    // a bit of a hack to do from here, as is doing it from Data and passing
    // it here, so...
    //XXX: Watch out for timezone conversion?  (Not important just now since
    //     we only use timestamp for ordering, but still might want to check
    //     that we're doing the right thing.)

    //print_r($q);
    if (!$this->_exec($s,$q, "Failed to insert into thread index")) return IGNORE_INDEX_FAILURES;
    return TRUE;
  }

  function thread_post_insert($data,$id)
  {
    $sphinx = $this->_connect();
    if (!$sphinx) return TRUE;

    $body = $data['body'];
    if (strlen($body) > MAX_SIZE_TO_INDEX) $body = substr($body, 0, MAX_SIZE_TO_INDEX);

    $s = $sphinx->prepare("INSERT INTO thread_post (id, body, member_id, thread_id, date_posted) VALUES (?, ?, ?, ?, ?)");
    $q = array($id, $body, $data['member_id'], $data['thread_id'], strtotime($data['date_posted']));
    // (See notes on timestamp in thread_insert().)

    if (!$this->_exec($s, $q, "Failed to insert into post index")) return IGNORE_INDEX_FAILURES;
    return TRUE;
  }

  function message_insert($data,$id) { return TRUE; }
  function message_post_insert($data,$id) { return TRUE; }

  function thread_update($data)
  {
    //XXX: I think this isn't presently used anywhere.  If it is, it
    //     should probably have the thread ID included too.  Just
    //     fail for now.
    return FALSE;
  }

  function thread_post_update($data,$id)
  {
    $sphinx = $this->_connect();
    if (!$sphinx) return TRUE;
    return $this->_update_post($sphinx, $id, $data['body']) || IGNORE_INDEX_FAILURES;
  }

  function message_update($data) { return TRUE; }
  function message_post_update($data) { return TRUE; }

  function thread_delete($id)
  {
    //XXX: Not sure what the semantics are for this, because I don't think
    //     it's ever actually called.  Just fail for now.
    return FALSE;
  }

  function thread_post_delete($id)
  {
    //NOTE: I don't think is currently ever actually called, but unlike some
    //      of the other functions, I think we can make a safe guess as to
    //      what it should do.

    $sphinx = $this->_connect();
    if (!$sphinx) return TRUE;

    // Rather than actually DELETE the record and leave a hole, let's just
    // clear out the content.
    return $this->_update_post($sphinx, $id, '') || IGNORE_INDEX_FAILURES;

    /*
    (Old code that actually deletes the record and leaves a hole.)
    $id = intval($id);
    $s = $sphinx->prepare("DELETE FROM thread_post WHERE id=" . $id);
    if (!$this->_exec($s, "Failed to delete from post index")) return FALSE;
    return TRUE;
    */
  }

  function message_delete($id) { return TRUE; }
  function message_post_delete($id) { return TRUE; }
}
