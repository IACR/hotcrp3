<?php
require "init.php";
require_once "finalLib.php";
global $Opt;
$dbName = $Opt['dbName'];
$shortName = $Opt['shortName'];
// include "includes/header.inc"; We don't use this because we need to insert things
// in the <head> section
function editButton() {
  echo <<<EOB
style="cursor:pointer;padding-bottom:8px;" src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAxMDAg
MTAwIj4gPHJlY3QgaGVpZ2h0PSIxMDAiIHdpZHRoPSIxMDAiIGZpbGw9Im5vbmUiLz4gPHBhdGgg
ZmlsbD0iIzFENzA3QSIgc3Ryb2tlPSJub25lIiBkPSJNMTAgOTAgdiAtMTUgbCA1MCAtNTAgbCAx
NSAxNSBsIC01MCA1MHoiLz4gPHBhdGggZmlsbD0iIzFENzA3QSIgc3Ryb2tlPSJub25lIiBkPSJN
NjUgMjAgbCAxMCAtMTAgYSAyIDIgLTkwIDAgMSAyIDAgbCAxMyAxMyBhIDIgMiA5MCAwIDEgMCAy
IGwgLTEwIDEweiIvPiA8L3N2Zz4="
EOB;
}

function plus() {
  echo <<<EOF
style="cursor:pointer;" src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAxMDAg
MTAwIj4gPGRlZnM+IDxjbGlwUGF0aCBpZD0iY2lyY2xlIj4gPGNpcmNsZSBjeD0iNTAiIGN5PSI1
MCIgcj0iNTAiLz4gPC9jbGlwUGF0aD4gPC9kZWZzPiA8cmVjdCB3aWR0aD0iMTAwIiBoZWlnaHQ9
IjEwMCIgZmlsbD0icmdiKDI0LCAxODgsIDE1NiwgLjgpIiBjbGlwLXBhdGg9InVybCgjY2lyY2xl
KSIvPiA8bGluZSB4MT0iMjAiIHgyPSI4MCIgeTE9IjUwIiB5Mj0iNTAiIHN0cm9rZS13aWR0aD0i
OCIgc3Ryb2tlPSJyZ2IoMjU1LDI1NSwyNTUsLjkpIi8+IDxsaW5lIHkxPSIyMCIgeTI9IjgwIiB4
MT0iNTAiIHgyPSI1MCIgc3Ryb2tlLXdpZHRoPSI4IiBzdHJva2U9InJnYigyNTUsMjU1LDI1NSwu
OSkiLz4gPC9zdmc+"
EOF;
}
echo <<<EOH
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>LNCS Proceedings Editor</title>
    <link href="../stylesheets/style.css" rel="stylesheet">
    <link href="https://iacr.org/libs/css/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../stylesheets/iacr.css" rel="stylesheet">
    <link href="https://iacr.org/tools/program/dependencies/dragula/dragula.css" rel="stylesheet">
    <link rel="shortcut icon" href="https://iacr.org/favicon.ico">
    <style>
     /* hotcrp overrides btn and btn-primary, so we use .button
        and .button-primary and copy from bootstrap 4.3. */
     .button {
        background-image: none;
        display: inline-block;
        font-weight: 400;
        color: #212529;
        text-align: center;
        vertical-align: middle;
        -webkit-user-select: none;
        -moz-user-select: none;
       -ms-user-select: none;
       user-select: none;
       background-color: transparent;
       border: 1px solid transparent;
       padding: 0.375rem 0.75rem;
       font-size: 1rem;
       line-height: 1.5;
       border-radius: 0.25rem;
       transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
     }
     .btn-danger {
       color: #ff0000;
     }
     @media (prefers-reduced-motion: reduce) {
       .button {
         transition: none;
       }
     }
     .button:hover {
        background: none;
        background-image: none;
        color: #212529;
        text-decoration: none;
     }
     .button-primary {
        color: #fff;
        background-color: #007bff;
        border-color: #007bff;
     }
     .button-primary:hover {
        color: #fff;
        background-color: #0069d9;
        border-color: #0062cc;
     }
     .button-primary:focus, .button-primary.focus {
        box-shadow: 0 0 0 0.2rem rgba(38, 143, 255, 0.5);
     }
     .button-warning {
        color: #212529;
        background-color: #ffc107;
        border-color: #ffc107;
     }
     .button-warning:hover {
        color: #212529;
        background-color: #e0a800;
        border-color: #d39e00;
     }
     .button-warning:focus, .btn-warning.focus {
        box-shadow: 0 0 0 0.2rem rgba(222, 170, 12, 0.5);
     }
     .list-group {
        min-height: 100px;
        background-color: #d0d0d0;
     }
     .topicList {
       min-height: 100px;
       background-color: #d0d0d0;
       margin: 10px;
     }
     .paperList {
       margin:10px;
     }
     .list-group:empty:before {
        color: green;
        content: attr(data-placeholder);
     }
     .volume {
       border: 1px solid silver;
       padding: 5px;
       border-radius: 5px;
       background-color: #e0f0e0;
     }
     .topic {
       background-color: lightyellow;
       padding: 5px;
       margin-bottom:10px;
     }
     .dropdown-item:hover {
       background-color: #d4edda;
     }
     .dropdown-item-danger:hover {
       background-color: #f8d7da;
     }
    </style>
    <script src="https://iacr.org/libs/js/jquery/3.3.1/jquery.min.js"></script>
    <script src="https://iacr.org/libs/css/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://iacr.org/libs/js/handlebars/handlebars-v4.1.0.js"></script>
    <script src="https://iacr.org/tools/program/dependencies/dragula/dragula.js"></script>
  </head>
<body><div id="top"><div id="header-site"><a class="q" href="/$dbName/"><span class="header-site-name">$shortName Home</span></a></div>
<div class="mt-4 float-right align-items-center d-flex">
<span id="save_status"></span>
<div class="ml-4 dropdown">
  <button class="button button-primary dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
    Actions
  </button>
  <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
    <a id="downloadMenu" class="dropdown-item disabled" href="createFrontMatter.php" download="frontmatter.tex" title="Download LaTex file when papers are assigned on the right">Download LaTeX</a>
    <a class="dropdown-item dropdown-item-danger" onclick="startOver();" title="Discard your work and start over">Start over</a>
    <a class="dropdown-item" onclick="$('#helpModal').modal()">Help</a>
  </div>
</div>
<h2 class="mx-4">IACR LNCS Editor</h2></div></div>
EOH;
$dbname = $Opt['dbName'];

try {
  $db = new PDO("mysql:host=localhost;dbname=$dbname;charset=utf8", $Opt['dbUser'], $Opt['dbPassword']);
  // outcome>0 and timeWithdrawn = 0 corresponds to an accepted paper. optionId is determined when create_conf.py is
  // used to set up the instance. It indicates that a final version was uploaded.
  $optionId = getFinalPaperOptionId();
  $sql = "select paperId,title from Paper where outcome>0 and timeWithdrawn = 0 and paperId not in (select paperId from PaperOption where optionId = :optionId)";
  $stmt = $db->prepare($sql);
  $stmt->bindParam(':optionId', $optionId);
  $papers = $stmt->fetchAll(PDO::FETCH_ASSOC);
  if ($papers && count($papers) > 0) {
    echo "<div class='mt-5 alert alert-warning'><p>Warning: the following accepted papers appear to have not uploaded their final versions yet:</p><ul>";
    foreach($papers as $paper) {
      echo "<li><a href=\"../paper/" . $paper['paperId'] . "\">" . $paper['title'] . "</a></li>";
    }
    echo "</ul></div>";
  } else {
    echo '<div class="mt-5 mx-3 alert alert-success alert-dismissible">All final versions have been uploaded. Create your LNCS proceedings by adding topics to the volumes,
    and dragging papers from the left into the topics. You can also move topics between volumes. Each volume should not exceed 500 pages.<button type="button" class="close bg-success" data-dismiss="alert" aria-label="Close">
    <span aria-hidden="true">&times;</span>  </button></div>';
  }
  $db = null;
} catch (PDOException $e) {
  echo $e->message();
}
?>
<!-- This is a handlebars "partial" template that is used to render a talk. -->
<script id="paper-partial" type="text/x-handlebars-template">
  <div class="paper inPaper list-group-item" id="paper-{{paperId}}" style="cursor:grab;">
    <strong class="paperTitle inPaper">{{title}}</strong><br>
    <a class="inPaper" target="_paper" href="../paper/{{paperId}}">View paper</a> ({{pages}} pages)
    <div class="inPaper authorList">
      {{#each authorlist}}
      <span class="authorName">{{name}} ({{affiliation}})</span>
      {{/each}}
    </div>
    {{#if topics }}
    <div class="topics">
      Keywords: <span class="keywords">{{topics}}</span>
    </div>
    {{/if}}
  </div>
  <!-- end partial template -->
</script>
<div id="loading" class="d-flex justify-content-center" role="status">
  <div class="spinner-border m-4"></div><span>Loading data...</span>
</div>
<div class="container-fluid float-left">
  <div id ="lncs" class="row">
    <!-- data gets written here for the volumes -->
  </div>
</div>
<!-- template to write out the volumes. -->
<script id="lncs-template" type="text/x-handlebars-template">
  <div class="col-5 bg-light overflow-auto" style="max-height:80vh">
    <h4 class="mt-3">Volumes:</h4>
    <ol>
    {{#each volumes }}
    <li><span class="text-primary" onclick="scrollShow('volume-{{@index}}');">Volume {{name}}</span>. {{#if totalPages}}({{totalPages}} pages in {{topics.length}} topics){{/if}}
      {{#if topics}}
      <ol>
      {{#each topics}}
      <li>({{papers.length}} papers) {{name}}</li>
      {{/each}}
      </ol>
      {{/if}}
    </li>
    {{/each}}
    </ol>
    <input type="text" class="form-control" id="search" placeholder="Filter unassigned papers">
    <h4 class="mt-2">Unassigned papers ({{unassigned_papers.length}})</h4>
    <ul id="unassigned" class="list-group" placeholder="drop papers here">{{#each unassigned_papers}}{{> paper}}{{/each}}</ul>
  </div>  
  <div class="col-7 bg-light overflow-auto" style="max-height:80vh">
     <h5 class="p-1 mr-2">
      Add a volume: <img onclick="editVolume('')" class="ml-3" width="24" title="Add a volume" <?php plus();?>></h5>
  {{#each volumes}}
  <div class="mb-2 volume" id="volume-{{@index}}">
    <h5 class="d-inline-block p-1 mr-2">Volume <span>{{name}}</span></h5>
      <img onclick="editVolume({{@index}});" class="ml-3" width="20" title="Edit volume number" <?php editButton();?>>
          <span onclick="showDelete('volume', '{{@index}}')" class="ml-3" style="cursor:pointer;font-size:18px;color:green;" title="Remove volume. All contained papers will be unassigned.">&#10005;</span>
    <div class="d-flex align-items-baseline">
     <h5 class="p-1 mr-2"><!--Volume {{counter @index}}-->
      Add a topic in this volume: <img onclick="editTopic({{@index}}, '')" class="ml-3" width="24" title="Add a topic" <?php plus();?>></h5>
      {{#if totalPages}}({{totalPages}} pages in {{topics.length}} topics){{/if}}
<!--      <span onclick="showDelete('volume', {{@index}})" style="cursor:pointer;font-size:18px;color:green;" title="Remove volume. All contained papers will be unassigned.">&#10005;</span>-->
    </div>
    <div class="topicList" id="topicList-{{@index}}">
      {{#if topics}}
      {{#each topics}}
      <div class="topic" id="topic-{{@../index}}-{{@index}}">
        <h5 class="d-inline-block"><span>{{name}}</span> ({{papers.length}} papers)
          <img onclick="editTopic({{@../index}}, {{@index}});" class="ml-3" width="20" title="Edit topic name" <?php editButton();?>>
          <span onclick="showDelete('topic', '{{@../index}}' + '-' + '{{@index}}')" class="ml-3" style="cursor:pointer;font-size:18px;color:green;" title="Remove topic. All contained papers will be unassigned.">&#10005;</span>
        </h5>
        <div id="paperlist-{{@../index}}-{{@index}}" class="list-group paperList" placeholder="drop papers here">{{#each papers}}{{> paper}}{{/each}}</div>
      </div>
      {{/each}}
      {{else}}
      <div class="d-flex justify-content-center bg-light">
      -- no topics in this volume --
      </div>
      {{/if}}
    </div>
  </div>
  {{/each}}
  </div>
</script>
<!-- end of template -->
<script>
 var lncsData = null;
 let theTemplateScript = $("#lncs-template").html();
 var theTemplate = Handlebars.compile(theTemplateScript);
 Handlebars.registerPartial("paper", $('#paper-partial').html());
 Handlebars.registerHelper( 'eachInMap', function ( map, block ) {
   var out = '';
   Object.keys( map ).map(function( prop ) {
      out += block.fn( {key: prop, value: map[ prop ]} );
   });
   return out;
 } );
 Handlebars.registerHelper("counter", function (index){
   return index + 1;
 });
 $.getJSON('getLNCSData.php', function(data) {
   console.dir(data);
   lncsData = data;
   if (data.volumes.length == 0) {
     $('#volumeNumbersModal').modal();
   }
   $('#loading').remove();
   reDraw();
 });

 function startOver() {
   $('#startOverModal').modal();
 }

 function reallyStartOver() {
   $('#startOverModal').modal('hide');
   $.ajax({
     type: 'POST',
     url: 'saveLNCSData.php',
     data: {'delete': true},
     dataType: 'json',
     beforeSend: function(jqXHR, settings) {
       $('#save_status').html('Deleting');
     },
     success: function(data, textStatus, jqxhr) {
       console.dir(data);
       if (data.hasOwnProperty('error')) {
         $('#save_status').html(data.error);
       } else {
         console.log('reloading');
         $('#save_status').html('Loading');
         window.location.reload();
       }
     },
     error: function(jqxhr, textStatus, error) {
       $('#save_status').html(textStatus);
     }});
 }
 

 function showDelete(type, id) {
   $('.deleteObject').each(function(index, obj) {$(this).text(type)});
   $('#deleteType').val(type);
   $('#deleteId').val(id);
   $('#deleteModal').modal();
 }

 function deleteObject() {
   let type = $('#deleteType').val();
   let id = $('#deleteId').val();
   if (type === 'volume') {
     let volume = lncsData.volumes[id];
     // find all topics under the volume, and move all papers to unassigned.
     for (let i=0; i < volume.topics.length; i++) {
       let paperList = volume.topics[i].papers;
       let papers = paperList.splice(0, paperList.length);
       Array.prototype.push.apply(lncsData.unassigned_papers, papers);
     }
     // Now remove all of the topics from the volume
     volume.topics.splice(0, volume.topics.length);
     // finally remove the volume
     lncsData.volumes.splice(id, 1);
   } else if (type === 'topic') {
     let parts = id.split('-');
     let paperList = lncsData.volumes[parts[0]].topics[parts[1]].papers;
     let papers = paperList.splice(0, paperList.length);
     Array.prototype.push.apply(lncsData.unassigned_papers, papers);
     lncsData.volumes[parts[0]].topics.splice(parts[1], 1);
   } else {
     console.log('unknown type: ' + type);
   }
   reDraw();
   $('#deleteModal').modal('hide');
 }
 
 function reDraw() {
   // We calculate total number of pages per volume.
   for (let i=0; i < lncsData.volumes.length; i++) {
     let totalPages = 0;
     for (let j = 0; j < lncsData.volumes[i].topics.length; j++) {
       for (let k = 0; k < lncsData.volumes[i].topics[j].papers.length; k++) {
         totalPages += lncsData.volumes[i].topics[j].papers[k].pages;
       }
     }
     lncsData.volumes[i].totalPages = totalPages;
   }
   $('#lncs').html(theTemplate(lncsData));
   addDrag();
   save();
   if (lncsData.unassigned_papers.length == 0) {
     $('#downloadMenu').removeClass('disabled');
   } else {
     $('#downloadMenu').addClass('disabled');
   }
   $('#search').on('input',function(e){
     let input = e.target.value;
     let filter = input.toUpperCase()
     $('#unassigned .list-group-item').each(function() {
       let li = $(this)
       let title = li.find('.paperTitle')
       let topics = li.find('.keywords');
       if(topics.text().toUpperCase().indexOf(filter) > -1 ||
          title.text().toUpperCase().indexOf(filter) > -1) {
         li.removeClass('d-none')
       } else {
         li.addClass('d-none');
       }
     });
   });
 }

 function save() {
   $.ajax({
     type: 'POST',
     url: 'saveLNCSData.php',
     data: {'json': JSON.stringify(lncsData, null, 2)},
     dataType: 'json',
     beforeSend: function(jqXHR, settings) {
       $('#save_status').html('Saving');
     },
     success: function(data, textStatus, jqxhr) {
       if (data.hasOwnProperty('error')) {
         $('#save_status').html(data.error);
       } else {
         $('#save_status').html('Saved at ' + new Date().toLocaleTimeString());
       }
     },
     error: function(jqxhr, textStatus, error) {
       $('#save_status').html(textStatus);
     }});
 }
 
 function movePaper(el, target, source, sibling) {
   let paperId = el.id.split('-')[1];
   let targetId = target.id;
   let sourceId = source.id;
   let talk = undefined;
   if (source.id === 'unassigned') {
     talkIndex = lncsData.unassigned_papers.findIndex((t) => {return t.paperId == paperId});
     if (talkIndex === -1) {
       console.log('cannot find talk in unassigned');
       return;
     } else {
       talk = lncsData.unassigned_papers.splice(talkIndex, 1)[0];
     }
   } else {
     let parts = source.id.split('-');
     let volumeIndex = parts[1];
     let topicIndex = parts[2];
     let talkIndex = lncsData.volumes[volumeIndex].topics[topicIndex].papers.findIndex((t) => {return t.paperId == paperId});
     if (talkIndex === -1) {
       console.log('cannot find talk in ' + source.id);
       return;
     }
     talk = lncsData.volumes[volumeIndex].topics[topicIndex].papers.splice(talkIndex, 1)[0];
   }
   // now we have talk, so put it in the target.
   let targetArray = undefined;
   if (target.id === 'unassigned') {
     targetArray = lncsData.unassigned_papers;
   } else {
     let parts = target.id.split('-');
     let volumeIndex = parts[1];
     let topicIndex = parts[2];
     targetArray = lncsData.volumes[volumeIndex].topics[topicIndex].papers;
   }
   if (sibling === null) {
     targetArray.push(talk);
   } else {
     let siblingIndex = targetArray.findIndex((t) => {return 'paper-' + t.paperId == sibling.id});
     if (siblingIndex === -1) {
       console.log('CANNOT FIND SIBLING');
       targetArray.push(talk);
       return;
     }
     targetArray.splice(siblingIndex, 0, talk);
   }
 }
 
 function moveTopic(el, target, source, sibling) {
   let parts = el.id.split('-');
   let volumeIndex = parts[1];
   let topicIndex = parts[2];
   let topic = lncsData.volumes[volumeIndex].topics.splice(topicIndex, 1)[0];
   if (sibling) {
     parts = sibling.id.split('-');
     lncsData.volumes[parts[1]].topics.splice(parts[2], 0, topic);
   } else {
     parts = target.id.split('-');
     lncsData.volumes[parts[1]].topics.push(topic);
   }
 }

 function addDrag() {
   let paperLists = Array.prototype.slice.call(document.querySelectorAll('.list-group'));
   dragula(paperLists).on('drop', function(el, target, source, sibling) {
     // Save the targetId, because the target element is invalid after the redraw.
     let targetId = target.id;
     movePaper(el, target, source, sibling);
     reDraw();
     document.getElementById(targetId).scrollIntoView({behavior: 'smooth'});
   });
   let topics = Array.prototype.slice.call(document.querySelectorAll('.topicList'));
   dragula(topics, {
     moves: function(el, container, handle) {
       return handle.classList.contains('topic');
     }}).on('drop', function(el, target, source, sibling) {
       moveTopic(el, target, source, sibling);
       reDraw();
   });
 }
 
 function editVolume(volIndex) {
    if (volIndex !== '') {
      let id = 'volume-' + volIndex;
      let val = $('#' + id + ' h5 span').text();
      console.log(val);
      $('#volNumber').val(val);
      $('#volIndex').val(volIndex);
    } else {// new volume
      $('#volNumber').val('');
      $('#volIndex').val(-1);
    }
    $('#editVolumeWarning').hide();
    $('#editVolumeModal').modal();
 }

 function editTopic(volumeIndex, topicIndex) {
   if (topicIndex !== '') {
     let id = 'topic-' + volumeIndex + '-' + topicIndex;
     $('#topicName').val($('#' + id + ' h5 span').text());
     $('#volumeIndex').val(volumeIndex);
     $('#topicIndex').val(topicIndex);
   } else {
     $('#topicName').val('');
     $('#volumeIndex').val(volumeIndex);
     $('#topicIndex').val('');
   }
   $('#editTopicWarning').hide();
   $('#editTopic').modal();
 }
 
  function updateVolume() {
    let volNumber = $('#volNumber').val()
    if (volNumber) {
      let volumeIndex = parseInt($('#volIndex').val());
      if (volumeIndex != -1) {
        lncsData.volumes[volumeIndex].name = volNumber;
      } else {
        lncsData.volumes.push({'name': volNumber, 'topics': [], 'totalPages': 0});
      }
      $('#editVolumeModal').modal('hide');
      reDraw();
    } else {
      $('#editVolumeWarning').show();
    }
  }

  function scrollShow(id) {
    let el = document.getElementById(id);
    if (el) {
      console.log(el);
      el.scrollIntoView({behavior: 'smooth'});
    }
  }

  function updateTopic() {
   let topicName = $('#topicName').val();
   if (topicName) {
     let volumeIndex = parseInt($('#volumeIndex').val());
     let topicIndex = $('#topicIndex').val();
     if (topicIndex) {
       topicIndex = parseInt(topicIndex);
       lncsData.volumes[volumeIndex].topics[topicIndex].name = topicName;
     } else {
       // a new topic
       let topicList = lncsData.volumes[volumeIndex].topics;
       topicList.push({'name': topicName,
                       'papers': []});
       topicIndex = topicList.length - 1;
     }
     $('#editTopic').modal('hide');
     reDraw();
     let topicId = 'topic-' + volumeIndex + '-' + topicIndex;
     console.log(topicId);
     document.getElementById(topicId).scrollIntoView();
   } else {
     $('#editTopicWarning').show();
   }
 }
 function setVolumeNumbers() {
   let vols = $('#volumeNumberList').val().split(/[, ]+/);
   console.log(vols);
   if (vols.length == 0) {
     $('#volumeNumberListWarning').show();
     $('#volumeNumbersModal').modal();
     return;
   }
   let volNumbers = vols.map(Number);
   console.log(volNumbers);
   for (let i = 0; i < volNumbers.length; i++) {
     lncsData.volumes.push({'name': volNumbers[i],
                            'topics': [],
                            'totalPages': 0});
   }
   $('#volumeNumbersModal').modal('hide');
   reDraw();
 }
 
</script>
<div class="modal fade" id="volumeNumbersModal" tabindex="-1" role="dialog" aria-labelledby="volumeNumbersModalLabel">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-body">
        <p>
          Please enter your LNCS volume numbers that were supplied to you by
          Springer, separated by commas. For Crypto 2022, this would be
          13507,13508,13509,13510.
        </p>
        <input class="form-control" id="volumeNumberList" type="text" pattern="[0-9,]+" placeholder="e.g., 13507,13508,13509,13510">
        <div class="alert alert-warning" id="volumeNumberListWarning">Volume numbers are required</div>
      </div>
      <div class="modal-footer">
        <button type="button" onclick="setVolumeNumbers();this.blur()" class="btn btn-primary">Set volume numbers</button>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-body">
        <input type="hidden" id="deleteId">
        <input type="hidden" id="deleteType">
        <p>
          You are about to delete a <span class="deleteObject"></span>. Any papers in
          that <span class="deleteObject"></span> will be moved to the unassigned area.
        </p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
        <button type="button" onclick="deleteObject();this.blur()" class="btn btn-danger">Delete</button>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="editTopic" tabindex="-1" role="dialog" aria-labelledby="editTopicLabel">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h3 class="modal-title" id="editTopicLabel">
          Edit topic
        </h3>
      </div>
      <div class="modal-body">
        <form class="form-horizontal" onsubmit="return false;">
          <input type="hidden" id="volumeIndex" name="volumeIndex">
          <input type="hidden" id="topicIndex" name="topicIndex">
          <div class="form-group">
            <label for="topicName">Topic title</label>
            <input type="text" class="form-control" id="topicName" name="topicName">
          </div>
        </form>
      </div>
      <div class="alert alert-warning" id="editTopicWarning">A topic name is required</div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
        <button type="button" onclick="updateTopic();this.blur()" class="btn btn-success">Save</button>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="editVolumeModal" tabindex="-1" role="dialog" aria-labelledby="editVolumeLabel">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h3 class="modal-title" id="editVolumeLabel">
          Edit volume number
        </h3>
      </div>
      <div class="modal-body">
        <form class="form-horizontal" onsubmit="return false;">
          <input type="hidden" id="volIndex" name="volIndex">
          <div class="form-group">
            <label for="topicName">LNCS volume number</label>
            <input type="text" pattern="[0-9]+" class="form-control" id="volNumber" name="volNumber" placeholder="LNCS volume number">
          </div>
        </form>
      </div>
      <div class="alert alert-warning" id="editVolumeWarning">A volume number is required</div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
        <button type="button" onclick="updateVolume();this.blur()" class="btn btn-success">Save</button>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="helpModal" tabindex="-1" role="dialog" aria-labelledby="helpModalLabel">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h3 class="modal-title" id="helpModalLabel">
          Help on preparing LNCS volumes
        </h3>
      </div>
      <div class="modal-body">
        <p>
          The purpose of this editor is to help you create LNCS volumes, and
          organize topics within those volumes. The output from this editor will be
          a zip file containing a set of LaTeX files for the frontmatter of each volume.
        </p>
        <p>
          Preparation of the Springer LNCS volumes should follow the instructions
          provided by Springer, which have in the past been located at
          <a href="https://www.springer.com/gp/computer-science/lncs/editor-guidelines-for-springer-proceedings" target="_help">this URL</a>. Note that IACR uses their
          own copyright forms, so you should ignore the LNCS instructions for Copyright.
        </p>
        <p>
          You can create topics within the volumes with the plus button on a
          volume.  Then you drag papers from the left column into the
          topics. You can also drag the topics around inside the volumes to
          rearrange them.  The number of pages in each volume will be
          automatically updated, and the file is automatically saved after each
          change. Once you have finished assigning papers to topics, you will be
          able to download the LaTeX files.
        </p>
        <p>
          The data for your work is cached on the server, but if you want to start over,
          use the "Start over" menu item to clear out your work. This will
          re-fetch the list of final papers.
        </p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="startOverModal" tabindex="-1" role="dialog" aria-labelledby="startoverLabel">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h3 class="modal-title" id="startOverLabel">
          Start Over
        </h3>
      </div>
      <div class="modal-body">
        <form class="form-horizontal" onsubmit="return false;">
          <div class="form-group">
            You are about to erase your work on building the volume. This will clear
            the cache on the server and re-fetch the list of final papers.
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
        <button type="button" onclick="reallyStartOver();this.blur()" class="btn btn-danger">Erase and start over</button>
      </div>
    </div>
  </div>
</div>
</body>
</html>
