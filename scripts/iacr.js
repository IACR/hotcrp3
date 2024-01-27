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
  let checkboxes = document.querySelectorAll('.IACRFinal_PaperOption');
  for (let i = 0; i < checkboxes.length; i++) {
    removeClickEventOnCheckbox(checkboxes[i]);
  }
}

/*
* Always look to hide final version uploader.
*/
window.addEventListener('DOMContentLoaded', (event) => {
  console.log('event fired');
  let elem = document.querySelector('foodiv.settings-sf-final');
  // let elem = document.getElementById('final:uploader');
  if (elem) {
    console.log(elem);
    elem.style.display = 'none';
  } else {
    console.log('no uploader');
  }
});
