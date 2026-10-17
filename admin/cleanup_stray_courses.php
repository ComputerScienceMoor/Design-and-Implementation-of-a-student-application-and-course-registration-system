<?php
require_once __DIR__.'/../config.php';
require_once __DIR__.'/../functions.php';

if(empty($_SESSION['is_admin'])){
    echo '<div style="padding:20px;color:#c00">Admin access required.</div>';
    exit;
}

$db = db_connect();
$msg = '';
$err = '';

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $action = $_POST['action'] ?? '';
    
    if($action === 'remove_orphaned'){
        try{
            $db->begin_transaction();
            // Get orphaned courses (not linked to any program)
            $sql = "SELECT id FROM courses WHERE program_id IS NULL OR program_id NOT IN (SELECT id FROM programs)";
            $result = $db->query($sql);
            $orphaned_ids = [];
            while($row = $result->fetch_assoc()){
                $orphaned_ids[] = intval($row['id']);
            }
            
            if(!empty($orphaned_ids)){
                $ids_str = implode(',', $orphaned_ids);
                // Delete registrations for these courses
                $db->query("DELETE FROM student_course_registrations WHERE course_id IN ($ids_str)");
                // Delete from registration_audit
                $db->query("DELETE FROM registration_audit WHERE course_id IN ($ids_str)");
                // Delete courses
                $db->query("DELETE FROM courses WHERE id IN ($ids_str)");
                $db->commit();
                $msg = 'Removed '.count($orphaned_ids).' orphaned course(s).';
            } else {
                $msg = 'No orphaned courses found.';
            }
        } catch(Exception $e){
            $db->rollback();
            $err = 'Error: '.$e->getMessage();
        }
    }
    
    if($action === 'remove_unregistered'){
        try{
            $db->begin_transaction();
            // Get courses with no student registrations
            $sql = "SELECT c.id, c.name FROM courses c LEFT JOIN student_course_registrations scr ON c.id = scr.course_id WHERE scr.id IS NULL";
            $result = $db->query($sql);
            $unregistered_ids = [];
            $removed_courses = [];
            while($row = $result->fetch_assoc()){
                $unregistered_ids[] = intval($row['id']);
                $removed_courses[] = $row['name'];
            }
            
            if(!empty($unregistered_ids)){
                $ids_str = implode(',', $unregistered_ids);
                // Delete from registration_audit
                $db->query("DELETE FROM registration_audit WHERE course_id IN ($ids_str)");
                // Delete courses
                $db->query("DELETE FROM courses WHERE id IN ($ids_str)");
                $db->commit();
                $msg = 'Removed '.count($unregistered_ids).' unregistered course(s): '.htmlspecialchars(implode(', ', $removed_courses));
            } else {
                $msg = 'All courses have at least one registration.';
            }
        } catch(Exception $e){
            $db->rollback();
            $err = 'Error: '.$e->getMessage();
        }
    }
}

// Get all courses
$all_courses = $db->query("SELECT c.id, c.name, c.course_code, p.name as program_name, COUNT(scr.id) as registration_count FROM courses c LEFT JOIN programs p ON c.program_id = p.id LEFT JOIN student_course_registrations scr ON c.id = scr.course_id GROUP BY c.id ORDER BY p.name, c.name");
$all_courses_list = [];
while($row = $all_courses->fetch_assoc()){
    $all_courses_list[] = $row;
}

// Get orphaned courses count
$orphaned = $db->query("SELECT COUNT(*) as c FROM courses WHERE program_id IS NULL OR program_id NOT IN (SELECT id FROM programs)");
$orphaned_count = ($orphaned && $r = $orphaned->fetch_assoc()) ? intval($r['c']) : 0;

// Get unregistered courses
$unregistered = $db->query("SELECT c.id, c.name, c.course_code FROM courses c LEFT JOIN student_course_registrations scr ON c.id = scr.course_id WHERE scr.id IS NULL ORDER BY c.name");
$unregistered_courses = [];
while($row = $unregistered->fetch_assoc()){
    $unregistered_courses[] = $row;
}

?>
<?php include 'header.php'; ?>
<div class="form-box" style="max-width:1000px;">
    <h2>Cleanup Stray Courses</h2>
    <?php if($msg): ?><div class="success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
    <?php if($err): ?><div class="error"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>
    
    <div style="margin-bottom:30px;padding:15px;background:#f5f5f5;border-radius:8px">
        <h3 style="margin-top:0">All Courses (<?php echo count($all_courses_list); ?>)</h3>
        <?php if(!empty($all_courses_list)): ?>
            <table class="table" style="width:100%;font-size:0.9rem">
                <thead>
                    <tr>
                        <th>Course Name</th>
                        <th>Code</th>
                        <th>Program</th>
                        <th>Registrations</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($all_courses_list as $course): ?>
                        <tr style="<?php echo empty($course['program_name']) ? 'background:#ffeeee;' : ($course['registration_count'] == 0 ? 'background:#ffffee;' : ''); ?>">
                            <td><?php echo htmlspecialchars($course['name']); ?></td>
                            <td><?php echo htmlspecialchars($course['course_code'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($course['program_name'] ?? '—ORPHANED—'); ?></td>
                            <td><?php echo intval($course['registration_count']); ?></td>
                            <td><a href="courses.php?delete=<?php echo intval($course['id']); ?>" onclick="return confirm('Delete this course?')" style="color:#c00">Delete</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <div style="margin-bottom:30px;padding:15px;background:#fee;border-radius:8px;border:1px solid #f99">
        <h3 style="margin-top:0;color:#c00">⚠️ Orphaned Courses</h3>
        <p>Courses not linked to any program: <strong><?php echo $orphaned_count; ?></strong></p>
        <?php if($orphaned_count > 0): ?>
            <form method="post" style="display:inline">
                <input type="hidden" name="action" value="remove_orphaned">
                <button type="submit" class="btn" style="background:#c00;color:white;padding:8px 12px;border:none;border-radius:4px;cursor:pointer" onclick="return confirm('Permanently remove all orphaned courses? This cannot be undone.')">Remove All Orphaned Courses</button>
            </form>
        <?php else: ?>
            <p style="color:#666">✓ No orphaned courses</p>
        <?php endif; ?>
    </div>
    
    <div style="margin-bottom:30px;padding:15px;background:#fffee0;border-radius:8px;border:1px solid #ff9">
        <h3 style="margin-top:0">Unregistered Courses</h3>
        <p>Courses with no student registrations: <strong><?php echo count($unregistered_courses); ?></strong></p>
        <?php if(!empty($unregistered_courses)): ?>
            <table class="table" style="width:100%;margin-bottom:15px;font-size:0.9rem">
                <thead>
                    <tr>
                        <th>Course Name</th>
                        <th>Course Code</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($unregistered_courses as $course): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($course['name']); ?></td>
                            <td><?php echo htmlspecialchars($course['course_code'] ?? 'N/A'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <form method="post" style="display:inline">
                <input type="hidden" name="action" value="remove_unregistered">
                <button type="submit" class="btn" style="background:#ff9900;color:white;padding:8px 12px;border:none;border-radius:4px;cursor:pointer" onclick="return confirm('Permanently remove all unregistered courses? This cannot be undone.')">Remove All Unregistered Courses</button>
            </form>
        <?php else: ?>
            <p style="color:#666">✓ All courses have at least one registration</p>
        <?php endif; ?>
    </div>
    
    <hr style="margin:30px 0">
    <p><a href="index.php">← Back to Admin Home</a> | <a href="courses.php">→ Manage Courses</a></p>
</div>
<?php include 'footer.php'; ?>
