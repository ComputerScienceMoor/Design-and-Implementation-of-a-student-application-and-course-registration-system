import os
files=['index.php','dashboard.php','admin/index.php','admin/login.php','admin/header.php']
results={}
for f in files:
    path=os.path.join(os.getcwd(),f)
    ok=True
    msg=''
    try:
        s=open(path,'rb').read().decode('utf-8',errors='replace')
        open_tags=s.count('<?php')+s.count('<?')
        close_tags=s.count('?>')
        if close_tags!=open_tags:
            ok=False
            msg=f"Mismatched PHP tags: <?php/<? count={open_tags}, ?> count={close_tags}"
        # check for unbalanced parentheses/braces roughly
        if s.count('(')!=s.count(')'):
            ok=False
            msg+=('; Unbalanced parentheses' if msg else 'Unbalanced parentheses')
        if s.count('{')!=s.count('}'):
            ok=False
            msg+=('; Unbalanced braces' if msg else 'Unbalanced braces')
    except Exception as e:
        ok=False
        msg=str(e)
    results[f]=(ok,msg)
for f,(ok,msg) in results.items():
    print(f,':', 'OK' if ok else 'ERROR', msg)
