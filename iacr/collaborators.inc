<?php
/* ****************************************************************
   This is specific to IACR, and modifies the profile page to provide
   an extra option for importing the collaborators of a person.
   *************************************************************** */
?>
<script>
 function showError(msg) {
   let errorDiv = document.getElementById('iacrError');
   if (msg) {
     errorDiv.style.display = 'block';
     errorDiv.innerHTML = msg;
   } else {
     errorDiv.style.display = 'none';
     errorDiv.innerHTML = '';
   }
 }

 function getCryptoDbId() {
   /* If the current user has a tag cryptodb#12345, then return the id
    * 12345. We need to work a little bit with the html in order to find
    * the tag. Pay attention to the fact that the behavior is not the
    * same for regular users versus PC chairs
    */
   let cryptodb = null;
   let tags = document.getElementsByName('tags');
   let ts = null;
   if (tags.length) {
       /* pc chair (or sysadmin) case. Tags are in an input field */
       ts = tags[0].value.trim().split(' ');
   } else {
       /* regular user case. Tags are just a text node. */
       tags = document.getElementById('tags');
       if (tags) {
         /* Need to climb up one level */
         tags = tags.parentElement.childNodes;
         for (let j = 0; j < tags.length; j++) {
             t = tags[j];
             if (t.nodeType == Node.TEXT_NODE) {
                 ts = t.nodeValue.trim().split(' ');
                 break;
             }
          }
       }
   }
   if (ts) {
       for (let i = 0; i < ts.length; i++) {
           let parts = ts[i].split('#');
           if (parts.length == 2 && parts[0] == 'cryptodb') {
               cryptodb = parts[1];
           }
       }
   }
   return cryptodb
 }


 function fetchData() {
   let name = document.getElementById('firstName').value + ' ' + document.getElementById('lastName').value;
   let cryptodb = getCryptoDbId();

   showError(null);
   let url = null;
   if (cryptodb != null) {
     url = 'https://iacr.org/cryptodb/data/hotcrp/conflict.php?id=' + cryptodb;
   } else if (name != null) {
     url = 'https://iacr.org/cryptodb/data/hotcrp/conflict.php?name=' + name;
   }
   if (url == null) {
     console.log('empty url');
     return;
   }
   fetch(url,
         {
           credentials: 'same-origin'
         })
     .then((response) => {
       if (response.status !== 200 || !response.ok) {
         throw Error('Unable to fetch data from ' + url);
       }
       return response.json();
     })
     .then((data) => {
       console.dir(data);
       let textareaNode = document.getElementsByName('collaborators')[0];
       // Don't add duplicates.
       let collab = new Set();
       if (textareaNode.value) {
         let lines = textareaNode.value.split(/\n/);
         for (let i = 0; i < lines.length; i++) {
           let line = lines[i].trim();
           if (line) {
             collab.add(line.replace(/\s{2,}/g, ' '));
           }
         }
       }
       if (data.error) {
         showError(data.error);
       } else {
         // remove parentheses from "IBM Research (Watson)"
         let coa = data.coauthors;
         for(let i = 0; i < coa.length; i++) {
           let line = coa[i]['name'] + ' ';
           let aff = coa[i]['affiliation'];
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
         showError('You may want to eliminate duplicates or add affiliations');
         textareaNode.setAttribute('rows', Math.max(5, collab.size));
         // sort to make it easier to spot duplicates
         textareaNode.value = Array.from(collab.values()).sort().join('\n');
       }
     }).catch(function(error) {
       console.dir(url);
       console.dir(error);
       showError(error.message);
     });
 }

</script>
<div class="g">
  <div style="display:none;margin:5px;padding: 5px;border: 1px solid red;color:red" id="iacrError"></div>
  <strong style="color:green">Import from IACR: </strong>
  <button type="button" class="ml-2 button button-primary" onclick="fetchData();return false">Fetch some recent coauthors</button>
</div>
  <div>
  <strong>NOTE:</strong>The button above is only assist to help you find your recent coauthors. It will not import
coauthors from ACM or other venues. It is your responsibility to supply your potential conflicts.
  </div>
