document.addEventListener('DOMContentLoaded', function(){
  const edu = document.querySelector('select[name="education_type"]');
  const hndSection = document.getElementById('hnd-section');
  const form = document.querySelector('form');
  const MAX_BYTES = 5 * 1024 * 1024; // 5MB

  function toggleHnd(){
    if(!edu || !hndSection) return;
    if(edu.value === 'hnd'){
      hndSection.style.display = '';
    } else {
      hndSection.style.display = 'none';
    }
  }

  if(edu && hndSection){
    edu.addEventListener('change', toggleHnd);
    toggleHnd();
  }


  const errorBox = document.getElementById('form-errors');
  function showError(message){
    if(errorBox){
      errorBox.innerText = message;
      errorBox.style.display = '';
      errorBox.scrollIntoView({behavior:'smooth', block:'center'});
    } else {
      alert(message);
    }
  }

  if(form){
    form.addEventListener('submit', function(e){
      if(edu && edu.value === 'hnd'){
        const ndInst = document.querySelector('input[name="nd_institution"]');
        const itPlace = document.querySelector('input[name="it_place"]');
        const itDuration = document.querySelector('input[name="it_duration"]');
        if(ndInst && !ndInst.value.trim()){ e.preventDefault(); showError('Please provide ND institution for HND applicants.'); ndInst.focus(); return; }
        if(itPlace && !itPlace.value.trim()){ e.preventDefault(); showError('Please provide place of IT.'); itPlace.focus(); return; }
        if(itDuration && !itDuration.value.trim()){ e.preventDefault(); showError('Please provide IT duration.'); itDuration.focus(); return; }
        const files = ['it_letter','nd_result','cgpa_evidence'];
        for(const fname of files){
          const el = document.querySelector('input[name="'+fname+'"]');
          if(el && el.files && el.files.length){
            const f = el.files[0];
            if(f.size > MAX_BYTES){ e.preventDefault(); showError('File too large for '+fname+' (max 5MB).'); return; }
            const ext = f.name.split('.').pop().toLowerCase();
            if(!['pdf','jpg','jpeg','png'].includes(ext)){ e.preventDefault(); showError('Invalid file type for '+fname+'. Allowed: PDF, JPG, PNG.'); return; }
          }
        }
      }
      // clear previous errors on submit
      if(errorBox) errorBox.style.display = 'none';
    });
  }
});
