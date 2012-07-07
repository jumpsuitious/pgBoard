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

  function _connect ()
  {
    if (!SPHINXQL_DSN) return FALSE;
    return new PDO(SPHINXQL_DSN, SPHINXQL_USER, SPHINXQL_PASSWORD);
  }

  function query($query,$index,$offset=0)
  {
    $sphinx = $this->_connect();
    if (!$sphinx) return TRUE;

    $empty = array("matches"=>array(), "total"=>0);

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
    if (strlen($data['subject']) > MAX_SIZE_TO_INDEX) return TRUE; // Don't index
    $sphinx = $this->_connect();
    if (!$sphinx) return TRUE;

    $s = $sphinx->prepare("INSERT INTO thread (id, subject, member_id, date_posted) VALUES (?, ?, ?, ?)");
    $q = array($id, $data['subject'], $data['member_id'], strtotime($data['date_posted']));
    // Using strtotime() is a bit of a hack.  What we really want to do is
    // get UNIX_TIMESTAMP(CURRENT_TIMESTAMP) from postgres, but that's also
    // a bit of a hack...
    //XXX: Watch out for timezone conversion?  (Not important just now since
    //     we only use timestamp for ordering, but still might want to check
    //     that we're doing the right thing.)

    //print_r($q);
    if (!$this->_exec($s,$q, "Failed to insert into thread index")) return FALSE;
    return TRUE;
  }

  function thread_post_insert($data,$id)
  {
    if (strlen($data['body']) > MAX_SIZE_TO_INDEX) return TRUE; // Don't index
    $sphinx = $this->_connect();
    if (!$sphinx) return TRUE;

    $s = $sphinx->prepare("INSERT INTO thread_post (id, body, member_id, thread_id, date_posted) VALUES (?, ?, ?, ?, ?)");
    $q = array($id, $data['body'], $data['member_id'], $data['thread_id'], strtotime($data['date_posted']));
    // (See notes on timestamp in thread_insert.)

    if (!$this->_exec($s, $q, "Failed to insert into post index")) return FALSE;
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
    if (strlen($data['body']) > MAX_SIZE_TO_INDEX)
    {
      return $this->thread_post_delete($id);
    }

    $sphinx = $this->_connect();
    if (!$sphinx) return TRUE;

    $id = intval($id);
    $s = $sphinx->prepare("SELECT member_id, thread_id, date_posted FROM thread_post WHERE id=" . $id);
    if (!$this->_exec($s, array(), "Failed to load post index")) return FALSE;
    $post = $s->fetch(PDO::FETCH_ASSOC);
    $post['id'] = $id;
    $post['body'] = $data['body'];

    $s = $sphinx->prepare("REPLACE INTO thread_post (id, body, member_id, thread_id, date_posted) VALUES (?, ?, ?, ?, ?)");
    $post = array($post['id'], $post['body'], $post['member_id'], $post['thread_id'], $post['date_posted']);
    //print_r($post);
    if (!$this->_exec($s, $post, "Failed to update post index")) return FALSE;

    return TRUE;
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
    $sphinx = $this->_connect();
    if (!$sphinx) return TRUE;

    $id = intval($id);
    $s = $sphinx->prepare("DELETE FROM thread_post WHERE id=" . $id);
    if (!$this->_exec($s, "Failed to delete from post index")) return FALSE;
    return TRUE;
  }

  function message_delete($id) { return TRUE; }
  function message_post_delete($id) { return TRUE; }
}
