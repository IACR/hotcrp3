<?php
require "init.php";
include "includes/header.inc";
?>
<div class="container-fluid float-left">
  <div class="row">
    <div class="col-4 col-lg-3 col-xl-2">
      <?php include "includes/leftnav.inc";?>
    </div>
    <div class="col-8">
      <h3>Import collaborators of the program committee</h3>
      <p>
        Before you open the site to submissions, you should import the recent
        collaborators of the program committee in order to detect conflicts
        of interest. This form uses CryptoDB to import coauthors but there
        may still be other conflicts.
      </p>
      <p>
        <button id="lookup-button" class="button button-primary" onclick="fetchData()">Import coauthors for program committee</button>
        <button id="submit-button" disabled class="button button-primary" onclick="document.getElementById('collform').submit();">Update collaborators</button>
      </p>
      <div class="w-75">
        <div class="progress form-group">
          <div id="lookupSuccess" class="progress-bar bg-success" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%;">0%</div>
          <div id="lookupFailure" class="progress-bar bg-warning" role="progressbar" aria-valuenow="0" area-valuemin="0" aria-valuemax="100" style="width: 0%;">0%</div>
        </div>
      </div>
      <p id="lookupStatus" class="font-weight-bold"></p>
      <dl>
      <?php
      global $Opt;
      $dbname = $Opt['dbName'];
      try {
        $db = new PDO("mysql:host=localhost;dbname=$dbname;charset=utf8", $Opt['dbUser'], $Opt['dbPassword']);
        $sql = "SELECT contactId,email,firstname,lastname,affiliation,collaborators,contactTags FROM ContactInfo WHERE (roles & 51) != 0 order by (roles & 5) DESC,lastname";
        $stmt = $db->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo '<form id="collform" action="updatecoll.php" method="POST">';
        foreach ($rows as $row) {
          echo '<input type="hidden" name="cryptodb-' . $row['contactId'] . '" id="cryptodb-' . $row['contactId'] . '">';
          echo '<dt class="pc_list_item" id="contact-' . $row['contactId'] . '" data-id="' . $row['contactId'] . '" data-tags="' . $row['contactTags'] . '"><a href="../profile/' . urlencode($row['email']) . '">' . $row['firstname'] . ' ' . $row['lastname'] . '</a> (<span id="aff-' . $row['contactId'] . '">' . $row['affiliation'] . '</span>) <span class="text-danger" id="error-' . $row['contactId'] . '"></span></dt>';
          echo '<dd style="margin-left:10px"><pre><textarea rows="5" class="form-input w-75" name="coll-' . $row['contactId'] . '" id="coll-' . $row['contactId'] . '">' . $row['collaborators'] . '</textarea></pre></dd>';
        }
        $db = null;
        } catch (PDOException $e) {
          echo $e->message();
        }
      ?>
      </dl>
    </div>
  </div>
</div>
<script>
 setActiveMenu('menucoll');

 // This class updates a progress bar with two bars #lookupSuccess and #lookupFailure
 // to show progress of lookups.  It keeps track of several counters:
 //  successCount (how many were successfully matched to a paper)
 //  errorCount (how many lookups failed because of a server error)
 //  failureCount (how many matches failed to find an answer (either from a lack of match
 //     or a server error)
 //
 //  It finishes when successCount + failureCount = totalCount
 function ProgressMonitor(totalCount, callback=null) {
   this.callback = callback;
   if (totalCount == 0) {
     $('#lookupStatus').text('Nothing to look up');
   } else {
     $('#lookupStatus').text('');
   }
   $('#lookupSuccess').css('width', '0%');
   $('#lookupFailure').css('width', '0%');
   $('#lookupSuccess').prop('aria-valuenow', 0);
   $('#lookupFailure').prop('aria-valuenow', 0);
   $('.progress').show();
   this.totalCount = totalCount;
   this.failureCount = 0;
   this.successCount = 0;
   // Errors are different than match failures. This indicates that
   // the ajax request had a failure.
   this.errorCount = 0;
   this.updateWidget = function() {
     var msg = this.successCount + ' found out of ' + this.totalCount;
     var successVal = Math.floor(100 * this.successCount / this.totalCount);
     var failureVal = Math.floor(100 * this.failureCount / this.totalCount);
     $('#lookupSuccess').prop('aria-valuenow', successVal);
     $('#lookupSuccess').css('width', String(successVal) + '%');
     $('#lookupSuccess').html(successVal + '%');
     $('#lookupFailure').prop('aria-valuenow', failureVal);
     $('#lookupFailure').css('width', String(failureVal) + '%');
     $('#lookupFailure').html(failureVal + '%');
     if (this.totalCount == this.failureCount + this.successCount) {
       msg = 'Finished! ' + msg;
       if (this.errorCount) {
         msg = msg + ' (' + this.errorCount + ' had server error(s))';
       }
       msg += '. You may wish to remove near-duplicates';
       if (this.callback != null) {
         this.callback();
       }
     }
     $('#lookupStatus').text(msg);
   };
   this.reportSuccess = function() {
     this.successCount++;
     this.updateWidget();
   }
   this.reportFailure = function() {
     this.failureCount++;
     this.updateWidget();
   }
   this.reportError = function() {
     this.failureCount++;
     this.errorCount++;
     this.updateWidget();
   }
 }

 /* Given a person object, fetch their collaborators from conflict.php. */
 function fetchPerson(person) {
   let url = 'https://iacr.org/cryptodb/data/hotcrp/conflict.php?requestId=' + person.id;
   if (person.cryptodb != null) {
     url += '&id=' + person.cryptodb;
   } else {
     url += '&name=' + encodeURI(person.name);
   }     
   return fetch(url).then(
     function(res) {
       if (res.ok && res.status === 200) {
         return res.json();
       }
       return Promise.resolve({'error': 'No response from API', requestId: person.id});
     },
     function(res) {
       Promise.resolve({'error': 'Fetch failed', requestId: person.id});
     }
   ).catch(
     function(err) {
       Promise.resolve({error: "Fetch operation failed",
                        person: person});
     });
 }

 /* Read the contents of the collaborators textarea and merge it with newly found
    collaborators.
  */
 function updateCollaborators(person, data) {
   console.dir(data);
   let textarea = document.getElementById('coll-' + data.requestId);
   // Don't add duplicates.
   let collab = new Set();
   if (textarea.value) {
     let lines = textarea.value.split(/\n/);
     for (let i = 0; i < lines.length; i++) {
       if (lines[i] != 'None' && lines[i]) {
         collab.add(lines[i].trim().replace(/\s{2,}/g, ' '));
       }
     }
   }
   // remove parentheses from "IBM Research (Watson)"
   for(let i = 0; i < data.coauthors.length; i++) {
     let line = data.coauthors[i]['name'] + ' ';
     let aff = data.coauthors[i]['affiliation'];
     if (aff) {
       aff = ' (' + aff.replace(/\(|\)/g, ' ') + ')';
     } else {
       aff = '';
     }
     line += aff;
     if (!collab.has(line)) {
       collab.add(line.trim().replace(/\s{2,}/g, ' '));
     }
   }
   if (data.affiliation != null) {
     let aff = data.affiliation.trim().replace(/\(|\)|s{2,}/g, ' ');
     collab.add('All (' + aff + ')');
   }
   if (person.affiliation != null) {
     let aff = person.affiliation.trim().replace(/\(|\)|s{2,}/g, ' ');
     collab.add('All (' + aff + ')');
   }
   if (collab.size == 0) {
     collab.add('None');
   }
   textarea.setAttribute('rows', Math.max(5, collab.size));
   // sort to make it easier to spot duplicates
   textarea.value = Array.from(collab.values()).sort().join('\n');
 }

 /* A single call to update all people in the form. This executes a bunch of ajax
    requests in parallel and updates the progress bar widget as it goes.
  */
 function fetchData() {
   var people = [];
   document.querySelectorAll('dt.pc_list_item').forEach(
     function(node, currentIndex, listObj) {
       let cryptodb = null;
       let id = node.dataset.id;
       if (node.dataset.tags) {
         let tags = node.dataset.tags.split(" ");
         tags.forEach(function(tag) {
           if (tag.trim().startsWith('cryptodb#')) {
             cryptodb = parseInt(tag.split("#")[1]);
           }
         });
       }
       people.push({id: parseInt(id),
                    cryptodb: cryptodb,
                    name: node.firstChild.innerText,
                    affiliation: document.getElementById('aff-' + id).innerText,
                    coll: node.nextSibling.firstChild.firstChild.value})
     });
   var progressMonitor = new ProgressMonitor(people.length, function() {
     document.getElementById('submit-button').disabled = false;
     document.getElementById('lookup-button').disabled = true;
   });
   Promise.all(people.map((person) => fetchPerson(person)))
          .then(dataList => dataList.map(data => {
            let ind = people.findIndex((person) => {return data.requestId === person.id});
            if (ind >= 0) {
              if (people[ind].name != data.fullname) {
                document.getElementById("error-" + data.requestId).innerHTML = 'Name found: ' + data.fullname;
              }
              if (data.hasOwnProperty('coauthors')) {
                progressMonitor.reportSuccess();
                // people[ind]['collaborators'] = data.coauthors;
                updateCollaborators(people[ind], data);
              } else {
                progressMonitor.reportFailure();
                if (data.hasOwnProperty('error')) {
                  document.getElementById("error-" + data.requestId).innerHTML = data.error;
                }
              }
            } else {
              progressMonitor.reportError();
              document.getElementById("error-" + data.requestId).innerHTML = 'Unknown problem';
              console.log('unable to find person');
              console.dir(data);
            }
          }));
 }

</script>
</body>
</html>
