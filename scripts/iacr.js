/* 
 *  A checkbox in HotCRP has a label that is also clickable. We want to make
 *  the checkbox unclickable, so we also have to remove the js-click-child
 *  class from the label of the checkbox.
 */
function removeClickEventOnCheckbox(cb) {
  // This will make the label unclickable.
  cb.parentNode.parentNode.parentNode.classList.remove('js-click-child');
  // This makes the checkbox unclickable. We can't make it disabled
  // because then it isn't submitted.
  cb.addEventListener('click', function(event) {
    event.preventDefault();
    event.stopImmediatePropagation();
    return true;
  });
}

/*
 *  This is used on the paper submission form.
 */
function iacrSubmitAndUploadCheckboxes() {
  let checkboxes = document.querySelectorAll('.IACRCB_PaperOption');
  for (let i = 0; i < checkboxes.length; i++) {
    removeClickEventOnCheckbox(checkboxes[i]);
  }
}

/*
 *  Check the resubmission URL field (PaperOption::IACR_SUBMISSIONURL_ID) on
 *  the paper submission form.
 */
addEventListener("load", () => {
  // Find the field by ID; fall back to the element ID made from its name.
  const urlfield = document.querySelector('textarea[name="opt3888"]')
    || document.querySelector('textarea#resubmission');
  if (!urlfield) {
    return;
  }
  const re = /^https:\/\/submit\.iacr\.org\/[a-zA-Z0-9_]+\/paper\/[0-9]{1,4}$/;
  function check() {
    // An empty field is fine: HotCRP checks required fields itself. A hidden
    // field is skipped, since the browser would still enforce the error and
    // block Save with no visible message.
    const ok = urlfield.value === ""
      || urlfield.offsetParent === null
      || re.test(urlfield.value);
    urlfield.setCustomValidity(ok ? "" : "The URL should have the form https://submit.iacr.org/<venue>/paper/<number>");
    return ok;
  }
  // Update silently while typing; show the message once the edit is done.
  urlfield.addEventListener("input", check);
  urlfield.addEventListener("change", () => {
    if (!check()) {
      urlfield.reportValidity();
    }
  });
  // A condition can hide the field after the URL was entered; recheck on
  // any click in the form, which runs before the browser validates on Save.
  if (urlfield.form) {
    urlfield.form.addEventListener("click", check, true);
  }
});
