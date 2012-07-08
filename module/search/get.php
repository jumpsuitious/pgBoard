<?php

function _parse_extra($extra)
{
  $r = array();
  if (!$extra) return $r;
  foreach (explode(",", $extra) as $term)
  {
    $term = explode("=", $term, 2);
    $r[$term[0]] = $term[1];
  }
  return $r;
}

function thread_get()
{
  _do_thread(cmd(2),cmd(3,true));
}

function thread_ex_get()
{
  _do_thread(cmd(3),cmd(4,true),cmd(2));
}

function _do_thread ($search, $page, $extra='')
{
  global $DB;
  $extra = _parse_extra($extra);

  $Search = new Search;

  $offset = $page?$page*100:0;
  $res = $Search->query($search,"thread",$offset,$extra);
  $ids = $res['matches'];
  $page += 1;

  $Query = new BoardQuery;
  $List = new BoardList;
  $List->type(LIST_THREAD_SEARCH);
  
  $List->title("Search Threads: ".htmlentities($search));
  $List->subtitle(number_format($res['total'])." results found showing ".($offset?$offset:1)."-".($offset+100).SPACE.ARROW_RIGHT.SPACE."page: $page");
  $List->header(false);
  require_once(DIR."module/search/.content/main.php");
  $List->header_menu();

  if($res['total'] == 0 || $offset > $res['total']) $ids = array(0);
  $DB->query($Query->list_thread(false,false,false,$ids));
  $List->data($DB->load_all());
  $List->thread();

  $List->footer();
}

function thread_post_get()
{
  _do_post(cmd(2),cmd(3,true),cmd(4,true));
}

function thread_post_ex_get()
{
  _do_post(cmd(3),cmd(4,true),cmd(5,true),cmd(2));
}

function _do_post ($search, $page, $limit, $extra='')
{
  global $DB;
  $extra = _parse_extra($extra);

  $Search = new Search;

  $offset = $page?$page*100:0;
  $res = $Search->query($search,"thread_post",$offset,$extra);
  $ids = $res['matches'];

  $Query = new BoardQuery;
  $View = new BoardView;
  $View->type(VIEW_THREAD_SEARCH);
  
  $View->title("Search Thread Posts: ".htmlentities($search));
  $View->subtitle(number_format($res['total'])." results found showing ".($offset?$offset:1)."-".($offset+100).SPACE.ARROW_RIGHT.SPACE."page: ". ($page+1));
  $View->header(false);
  require_once(DIR."module/search/.content/main.php");
  $View->header_menu();

  if($res['total'] == 0) $ids = array(0);
  $DB->query($Query->view_thread(false,$page,$limit,$ids));
  $View->data($DB->load_all());
  $View->thread();

  $View->footer();
}
?>
