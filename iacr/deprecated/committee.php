<?php
/* ****************************************************************
   This is specific to IACR, and modifies the account creation page
   to provide an extra option for importing the program committee
   from the conference website. The advantage of this is that it adds
   a tag on each user to indicate their cryptodb ID. These tags can then
   be used to import recent coauthors for conflict of interest, and
   it eliminates the need to create a csv with roles.
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

 function fetchComm() {
   showError(null);
   let sel = document.getElementById('iacrvenue');
   if (!sel.selectedIndex) {
     showError('no conference selected');
     return;
   }
   let url = 'https://';
   url += sel.options[sel.selectedIndex].value;
   url += '.iacr.org/';
   url += document.getElementById('year').value;
   url += '/json/comm.json';
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
     .then((jsdata) => {
       let txt = "name,email,affiliation,tags,roles\n";
       let c = jsdata['committee'];
       let re = new RegExp(',', 'g');
       for(let i = 0; i < c.length; i++) {
         txt += c[i]['name'];
         txt += ',EMAIL,';
         let aff = c[i]['affiliation'];
         if (aff) {
            aff = aff.replace(re, '');
         } else {
            aff = 'AFFILIATION';
         }
         txt += aff;
         txt += ',cryptodb#' + c[i]['id'];
         txt += ',pc\n';
       }
       let textarea = document.getElementsByName('bulkentry')[0];
       showError('You must add the email addresses by replacing EMAIL on each line. You may wish to change "pc" to "chair" if someone should be a program chair.');
       textarea.setAttribute('rows', 10);
       textarea.value = txt;
       let submitButton = document.getElementById('commSubmit');
       if (submitButton) {
          submitButton.removeAttribute('disabled');
       }
     }).catch(function(error) {
       console.dir(url);
       console.dir(error);
       showError(error.message);
     });
}

function updateButton() {
  var sel = document.getElementById('iacrvenue');
  if (!sel.selectedIndex) {
    document.getElementById('fetchIACR').disabled = true;
    return;
  }
  document.getElementById('fetchIACR').disabled = false;
}
</script>
<div class="g">
  <div style="display:none;margin-bottom:1rem;padding: 5px;border: 1px solid red;color:red" id="iacrError"></div>
  <strong> OR </strong>
  <strong style="color:green">import from IACR:</strong>
  <div class="collaborators-form">
    <select id="iacrvenue" onchange="updateButton()">
      <option>Select a conference</option>
      <option value="asiacrypt">asiacrypt</option>
      <option value="crypto">crypto</option>
      <option value="eurocrypt">eurocrypt</option>
      <option value="ches">ches</option>
      <option value="fse">fse</option>
      <option value="pkc">pkc</option>
      <option value="rwc">rwc</option>
      <option value="tcc">tcc</option>
    </select>
    <input type="number" id="year" id="year"
           value="<?php echo date('Y');?>"
           min="<?php echo intval(date('Y')) - 2;?>"
           max="<?php echo intval(date('Y')) + 3;?>">
    <button type="button" id="fetchIACR" class="button button-primary" onclick="fetchComm();return false" disabled>Fetch committee</button>
  </div>
</div>
