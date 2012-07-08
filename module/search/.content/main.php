<?php
if(!isset($res)) // if calling from within search itself don't include structure
{
  $Base = new Base;
  $Base->type(SEARCH);
  $Base->title("Search");
  $Base->header();
}
$Form = new Form;
$Form->ajax(false);
if(isset($_SESSION['search'])) $Form->values($_SESSION['search']);
$Form->header("/search/","post",FORM_SALT);
$Form->fieldset_open("Search Information");
$Form->add_text("search","Search For:",300);
if(!isset($res)) $Form->values(array("_type"=>"thread"));
$Form->add_select("_type","Within:",NULL,array("thread"=>"Threads","thread_post"=>"Thread Posts","message"=>"Messages","message_post"=>"Message Posts"));
$Form->fieldset_close();
$Form->fieldset_open("Optional Fields");
//print "<li>will return in a bit</li>\n";
$Form->add_text("member","By Member:");
$Form->labels(false);
print "<li>\n";
print "  <label>Date Range:</label>\n";
$Form->add_date("start",false);
$Form->add_date("end",false);
print "</li>\n";
$Form->labels(true);
$Form->add_select("quickdate","Quick Ranges:","Choose",array("prevweek"=>"Last 7 Days","thismonth"=>"This Month","thisyear"=>"This Year","lastyear"=>"Last Year"),"onchange=\"quickrange($(this).val())\">");

$Form->fieldset_close();

$Form->add_submit("Search");
$Form->footer();

$Form->header_validate();
$Form->add_notnull("search","Please enter a search term.");
$Form->add_notnull("_type","Please choose what to search.");
$Form->set_focus("search");
$Form->footer_validate();

if(!isset($res)) $Base->footer();
?>
<script type="text/javascript">
function quickrange(what)
{
  var startAt = new Date();
  var endAt = new Date(startAt.getTime());
  switch(what)
  {
    case "prevweek":
      startAt = new Date((new Date()).getTime() - 1000 * 60 * 60 * 24 * 7);
      break;
    case "thismonth":
      startAt.setDate(1);
      break;
    case "thisyear":
      startAt.setDate(1);
      startAt.setMonth(0);
      break;
    case "lastyear":
      startAt.setFullYear(startAt.getFullYear() - 1);
      startAt.setDate(1);
      startAt.setMonth(0);
      endAt.setFullYear(startAt.getFullYear());
      endAt.setDate(31);
      endAt.setMonth(11);
      break;

    default:
      $('#start').val('');
      $('#end').val('');
      return;
  }
  $('#start').val(startAt.toDateString().split(" ").slice(1).join(" "));
  $('#end').val(endAt.toDateString().split(" ").slice(1).join(" "));
}
</script>
