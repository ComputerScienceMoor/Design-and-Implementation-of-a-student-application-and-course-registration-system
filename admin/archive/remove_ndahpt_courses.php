<?php
// Archived: move of admin/remove_ndahpt_courses.php
require_once __DIR__.'/../../config.php';
require_once __DIR__.'/../../functions.php';

if(empty($_SESSION['is_admin'])){
    echo '<div style="padding:20px;color:#c00">Admin access required.</div>';
    exit;
}

$db = db_connect();
$msg = '';
$err = '';

// Find the program
$prog = $db->query("SELECT id, name FROM programs WHERE name LIKE '%National Diploma in Animal Health and Production Technology%'");
$program = null;
if($prog && $p = $prog->fetch_assoc()){
    $program = $p;
}

if($_SERVER['REQUEST_METHOD'] === 'POST' && $program){
    $action = $_POST['action'] ?? '';
    
    if($action === 'delete_courses'){
        try{
            $db->begin_transaction();
            $prog_id = intval($program['id']);
            
            // Get all courses for this program
            $courses_res = $db->query("SELECT id, name FROM courses WHERE program_id = $prog_id");
            $course_ids = [];
            $course_names = [];
            while($row = $courses_res->fetch_assoc()){
                $course_ids[] = intval($row['id']);
                $course_names[] = $row['name'];
            }
            
            if(!empty($course_ids)){
                $ids_str = implode(',', $course_ids);
                
                // Delete student registrations
                $db->query("DELETE FROM student_course_registrations WHERE course_id IN ($ids_str)");
                
                // Delete registration audit entries
                $db->query("DELETE FROM registration_audit WHERE course_id IN ($ids_str)");
                
                // Delete courses
                $db->query("DELETE FROM courses WHERE id IN ($ids_str)");
                
                $db->commit();
                $msg = 'Removed '.count($course_ids).' course(s) from '.htmlspecialchars($program['name']).': '.htmlspecialchars(implode(', ', $course_names));
            } else {
                $msg = 'No courses found for this program.';
            }
        } catch(Exception $e){
            $db->rollback();
            $err = 'Error: '.$e->getMessage();
        }
    }
}

// Get courses for this program
$program_courses = [];
if($program){
    $courses_res = $db->query("SELECT id, name, course_code FROM courses WHERE program_id = ".intval($program['id'])." ORDER BY name");
    if($courses_res){
        while($row = $courses_res->fetch_assoc()){
            $program_courses[] = $row;
        }
    }
}

?>
<?php include __DIR__.'/../header.php'; ?>
<div class="form-box" style="max-width:800px;">
    <h2>Remove Courses from Program (Archived)</h2>
    <?php if($msg): ?><div class="success" style="margin-bottom:20px"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
    <?php if($err): ?><div class="error" style="margin-bottom:20px"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>
    <p>This file has been archived from the active admin UI. It is kept for reference.</p>
    <?php if(!$program): ?>
        <div style="padding:20px;background:#fee;border:1px solid #f99;border-radius:8px;color:#c00">
            <strong>Program Not Found</strong>
            <p>The program "National Diploma in Animal Health and Production Technology" does not exist in the database.</p>
        </div>
    <?php else: ?>
        <div style="padding:15px;background:#f5f5f5;border-radius:8px;margin-bottom:20px">
            <strong style="display:block;margin-bottom:10px">Program:</strong>
            <p style="margin:0"><?php echo htmlspecialchars($program['name']); ?></p>
        </div>
        <?php if(empty($program_courses)): ?>
            <div style="padding:20px;background:#e8f5e9;border:1px solid #4caf50;border-radius:8px;color:#2e7d32">
                <strong>No Courses Found</strong>
                <p style="margin:10px 0 0 0">This program has no courses to remove.</p>
            </div>
        <?php else: ?>
            <div style="margin-bottom:20px">
                <h3 style="margin:0 0 12px 0">Courses for Reference (<?php echo count($program_courses); ?>)</h3>
                <table class="table" style="width:100%">
                    <thead>
                        <tr>
                            <th>Course Name</th>
                            <th>Course Code</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($program_courses as $course): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($course['name']); ?></td>
                                <td><?php echo htmlspecialchars($course['course_code'] ?? 'N/A'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    <hr style="margin:30px 0">
    <p><a href="../index.php">← Back to Admin</a> | <a href="../cleanup_stray_courses.php">→ View All Courses</a></p>
</div>
<?php include __DIR__.'/../footer.php'; ?>
