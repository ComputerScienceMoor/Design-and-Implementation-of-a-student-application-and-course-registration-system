<?php
require_once 'functions.php';
if(empty($_SESSION['user_id'])) redirect('login.php');

define('MAX_UPLOAD_BYTES', 5 * 1024 * 1024); // 5 MB per file
$allowed_mimes = ['application/pdf','image/jpeg','image/png'];
$allowed_exts = ['pdf','jpg','jpeg','png'];
$uploaddir = __DIR__.'/uploads';
if(!is_dir($uploaddir)) safe_mkdir($uploaddir,0755);

function respond_json($data){
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || !empty($_POST['ajax']);

    $uid = intval($_SESSION['user_id']);
    $db = db_connect();
    $o_path = null; $j_path = null;
    $messages = [];
    $errors = [];
    $movedFiles = [];

    $applicationDepartment = trim((string)($_POST['application_department'] ?? ''));
    $shouldUpdateApplication = !empty($_POST['action']) && $_POST['action'] === 'update_application_details';

    if($shouldUpdateApplication){
        if($applicationDepartment === ''){
            $errors[] = 'Please select your department.';
        } else {
            $resolvedProgramId = resolve_program_id_from_selection($db, $applicationDepartment);
            $stmt = $db->prepare('UPDATE users SET application_program_id = ?, application_department = ? WHERE id = ?');
            if($stmt){
                $stmt->bind_param('isi', $resolvedProgramId, $applicationDepartment, $uid);
                if($stmt->execute()){
                    $messages[] = 'Your application details were updated.';
                } else {
                    $errors[] = 'Unable to update your application details.';
                }
                $stmt->close();
            } else {
                $errors[] = 'Unable to save your application details.';
            }
        }
    }

    // helper to process one file input
    $process = function($field, $prefix) use(&$o_path,&$j_path,&$messages,&$errors,&$movedFiles,$allowed_mimes,$allowed_exts,$uid){
        if(empty($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) return null;
        if($_FILES[$field]['size'] > MAX_UPLOAD_BYTES){
            $errors[] = "File too large for $field (max 5MB)";
            return null;
        }
        $tmp = $_FILES[$field]['tmp_name'];
        $orig = $_FILES[$field]['name'];
        $type = mime_content_type($tmp);
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        if(!in_array($type, $allowed_mimes) || !in_array($ext, $allowed_exts)){
            $errors[] = "Invalid file for $field (type: $type ext: $ext)";
            return null;
        }
        $safe = $prefix.'_'.$uid.'_'.time().'_'.preg_replace('/[^a-zA-Z0-9._-]/','_', basename($orig));
        $destRel = 'uploads/'. $safe;
        $destAbs = __DIR__.'/'. $destRel;
        if(move_uploaded_file($tmp, $destAbs)){
            chmod($destAbs, 0644);
            $messages[] = "Uploaded $field";
            $movedFiles[] = $destAbs;
            return $destRel;
        } else {
            $errors[] = "Failed to move uploaded file for $field";
            return null;
        }
    };

    $o = $process('o_level','o'); if($o) $o_path = $o;
    $j = $process('jamb_result','j'); if($j) $j_path = $j;
    // optional second O-Level upload (keeps first as primary, store additional copy in student_other_documents)
    $o2 = $process('o_level_2','o2');
    if($o2){
        try{
            $stmt = $db->prepare('INSERT INTO student_other_documents (user_id, file_path, label) VALUES (?, ?, ?)');
            if($stmt){
                $lbl = 'O-Level Additional Copy';
                $stmt->bind_param('iss', $uid, $o2, $lbl);
                $stmt->execute();
                $stmt->close();
            }
        } catch(Exception $e){ /* ignore */ }
    }
    // Handle additional labeled uploads (one file per request allowed via 'other_file' + 'label')
    $other_path = null; $other_label = null;
    if(!empty($_FILES['other_file']) && $_FILES['other_file']['error'] === UPLOAD_ERR_OK){
        $other_path = $process('other_file','other');
        $other_label = trim($_POST['label'] ?? '');
        if($other_path){
            // persist into student_other_documents
            try{
                $stmt = $db->prepare('INSERT INTO student_other_documents (user_id, file_path, label) VALUES (?, ?, ?)');
                if($stmt){
                    $lbl = $other_label ?: 'Other Document';
                    $stmt->bind_param('iss', $uid, $other_path, $lbl);
                    $stmt->execute();
                }
            } catch(Exception $e){
                $errors[] = 'Failed to record other document';
            }
        }
    }

    // Update DB using transaction
    $statusPending = 'pending';
    $db->begin_transaction();
    try{
        // If prospective student is replacing an existing O-Level file,
        // archive the previous path into `student_other_documents` so history is preserved,
        // then update the user's o_level_path to the new upload.
        if($o_path){
            try{
                $chk = $db->prepare('SELECT o_level_path, status FROM users WHERE id = ? LIMIT 1');
                if($chk){
                    $chk->bind_param('i', $uid);
                    $chk->execute();
                    $resChk = $chk->get_result()->fetch_assoc();
                    $prevO = $resChk['o_level_path'] ?? null;
                    $uStatus = $resChk['status'] ?? null;
                    $chk->close();
                    if($prevO && $uStatus === 'prospective'){
                        // record previous o-level as an "other document" for audit
                        try{
                            $insPrev = $db->prepare('INSERT INTO student_other_documents (user_id, file_path, label) VALUES (?, ?, ?)');
                            if($insPrev){
                                $lbl = 'Previous O-Level Result';
                                $insPrev->bind_param('iss', $uid, $prevO, $lbl);
                                $insPrev->execute();
                                $insPrev->close();
                            }
                        } catch(Exception $e){ /* ignore archival failures */ }
                    }
                }
            } catch(Exception $e){ /* ignore check failures */ }
        }

        if($o_path && $j_path){
            $stmt = $db->prepare('UPDATE users SET o_level_path = ?, jamb_path = ? WHERE id = ?');
            if(!$stmt) throw new Exception('DB prepare failed');
            if(!$stmt->bind_param('ssi',$o_path,$j_path,$uid) || !$stmt->execute()) throw new Exception('DB exec failed');
        } elseif($o_path){
            $stmt = $db->prepare('UPDATE users SET o_level_path = ? WHERE id = ?');
            if(!$stmt) throw new Exception('DB prepare failed');
            if(!$stmt->bind_param('si',$o_path,$uid) || !$stmt->execute()) throw new Exception('DB exec failed');
        } elseif($j_path){
            $stmt = $db->prepare('UPDATE users SET jamb_path = ? WHERE id = ?');
            if(!$stmt) throw new Exception('DB prepare failed');
            if(!$stmt->bind_param('si',$j_path,$uid) || !$stmt->execute()) throw new Exception('DB exec failed');
        }
        // Do NOT change user's admission/status on document uploads.
        // Preserve existing `status`, `matric_no`, and registered courses.
        // However, if both O-Level and JAMB are present after this upload,
        // mark the application as 'pending' for Post-UTME review — but
        // only when this won't revoke an existing admission (do not change
        // status for already-admitted students with matric or registrations).
        try{
            $s = $db->prepare('SELECT o_level_path, jamb_path, status, matric_no FROM users WHERE id = ? LIMIT 1');
            if($s){
                $s->bind_param('i', $uid);
                $s->execute();
                $resu = $s->get_result()->fetch_assoc();
                $s->close();
            }
            $hasO = !empty($resu['o_level_path']);
            $hasJ = !empty($resu['jamb_path']);
            $curStatus = $resu['status'] ?? null;
            $hasMatric = !empty($resu['matric_no']);

            // check registrations exist
            $regCount = 0;
            $sr = $db->prepare('SELECT COUNT(*) FROM student_course_registrations WHERE user_id = ?');
            if($sr){
                $sr->bind_param('i', $uid);
                $sr->execute();
                $sr->bind_result($regCount);
                $sr->fetch();
                $sr->close();
            }

            if($hasO && $hasJ){
                // Only set to pending if user is not admitted/completed and has no matric or registrations
                if(!in_array($curStatus, ['new','completed'], true) && !$hasMatric && intval($regCount) === 0){
                    if(@update_user_status($db, $uid, $statusPending, 'upload')){
                        $messages[] = 'Your documents were received. Please wait for your Post-UTME result.';
                    }
                }
            }
        } catch(Exception $e){ /* ignore status update failures */ }
        $db->commit();
    } catch(Exception $e){
        $db->rollback();
        $errors[] = $e->getMessage();
        // remove moved files on failure
        foreach($movedFiles as $p) if(is_file($p)) @unlink($p);
    }

    // Persist server logs
    if(!empty($errors)){
        $logfile = __DIR__.'/logs/upload.log';
        @mkdir(dirname($logfile),0755,true);
        file_put_contents($logfile, date('[Y-m-d H:i:s] ').implode(' | ',$errors)."\n", FILE_APPEND);
    }

    // Respond
    if($isAjax){
        $ok = empty($errors);
        $paths = ['o'=>$o_path,'j'=>$j_path];
        if(isset($other_path)) $paths['other'] = $other_path;
        respond_json(['success'=>$ok,'messages'=>$messages,'errors'=>$errors,'paths'=>$paths]);
    } else {
        if(!empty($errors)) $_SESSION['flash_error'] = implode(' | ', $errors);
        elseif(!empty($messages)) $_SESSION['flash_success'] = implode(' | ', $messages);
        redirect('dashboard.php');
    }
}

redirect('dashboard.php');
