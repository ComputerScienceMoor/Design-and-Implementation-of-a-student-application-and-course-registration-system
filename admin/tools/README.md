These are development and debugging helper scripts moved from the admin root into this `tools/` folder to declutter the admin directory.

Files moved here:
- find_unmatched_brace.php
- find_unmatched_brace2.php
- find_unmatched_php_braces.php
- find_unmatched_stack.php
- php_brace_trace.php
- debug_user_cli.php

Purpose: quick local helpers for diagnosing unclosed braces and resolving user/course mappings. Keep them for development but do not run from public web routes in production.
