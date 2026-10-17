document.addEventListener('DOMContentLoaded', function(){
  const form = document.getElementById('uploadForm');
  if(!form) return;

  const status = document.getElementById('uploadStatus');
  const btn = form.querySelector('button[type=submit]');

  function setStatus(msg, type){
    status.innerHTML = '<div class="'+(type||'info')+'">'+msg+'</div>';
  }

  form.addEventListener('submit', function(e){
    e.preventDefault();
    const fd = new FormData(form);
    fd.append('ajax', '1');

    const xhr = new XMLHttpRequest();
    xhr.open('POST', form.action, true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    btn.disabled = true;
    setStatus('Uploading...', 'info');

    // progress bar
    let progressWrap = document.createElement('div');
    progressWrap.className = 'progress-wrap';
    progressWrap.innerHTML = '<div class="progress-bar"><div class="progress-fill" style="width:0%"></div></div>';
    status.appendChild(progressWrap);

    xhr.upload.onprogress = function(ev){
      if(ev.lengthComputable){
        const pct = Math.round((ev.loaded/ev.total)*100);
        progressWrap.querySelector('.progress-fill').style.width = pct + '%';
      }
    };

    xhr.onload = function(){
      btn.disabled = false;
      try{
        const res = JSON.parse(xhr.responseText);
        if(res.success){
          setStatus((res.messages||[]).join(' | ') || 'Upload successful', 'success');
          // optionally refresh parts of the page
          setTimeout(()=> location.reload(), 900);
        } else {
          setStatus((res.errors||[]).join(' | ') || 'Upload failed', 'error');
        }
      }catch(err){
        setStatus('Unexpected server response', 'error');
      }
    };

    xhr.onerror = function(){
      btn.disabled = false;
      setStatus('Upload failed due to network error', 'error');
    };

    xhr.send(fd);
  });
});
