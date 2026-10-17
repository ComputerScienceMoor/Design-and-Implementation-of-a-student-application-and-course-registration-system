<?php
require_once __DIR__.'/../functions.php';
if(empty($_SESSION['is_admin'])) redirect('login.php');
$db = db_connect();

// Show recent registration audits and current registrations
$audits = $db->query("SELECT ra.id, ra.user_id, ra.course_id, ra.action, ra.actor_ip, ra.created_at, u.email as user_email, c.name as course_name, c.course_code FROM registration_audit ra LEFT JOIN users u ON ra.user_id = u.id LEFT JOIN courses c ON ra.course_id = c.id ORDER BY ra.created_at DESC LIMIT 200");
$regs = $db->query("SELECT r.id, r.user_id, r.course_id, r.registered_at, u.email as user_email, c.name as course_name, c.course_code FROM student_course_registrations r LEFT JOIN users u ON r.user_id = u.id LEFT JOIN courses c ON r.course_id = c.id ORDER BY r.registered_at DESC LIMIT 200");

include 'header.php';
?>
  <h2>Registrations</h2>
  <div style="display:flex;gap:20px">
    <div style="flex:1">
      <div class="card">
        <div class="card-header"><h3 class="card-title">Recent Actions (Register / Deregister)</h3></div>
        <div class="card-body">
          <table>
            <tr><th>ID</th><th>User</th><th>Course</th><th>Action</th><th>IP</th><th>When</th></tr>
            <?php while($r = $audits->fetch_assoc()): ?>
              <tr>
                <td><?php echo $r['id']; ?></td>
                <td><?php echo htmlspecialchars($r['user_email'] ?: ('#'.$r['user_id'])); ?></td>
                <td><?php echo htmlspecialchars($r['course_name'] ?: ('#'.$r['course_id'])); ?> <?php if(!empty($r['course_code'])) echo '('.htmlspecialchars($r['course_code']).')'; ?></td>
                <td><?php echo htmlspecialchars($r['action']); ?></td>
                <td><?php echo htmlspecialchars($r['actor_ip']); ?></td>
                <td><?php echo htmlspecialchars($r['created_at']); ?></td>
              </tr>
            <?php endwhile; ?>
          </table>
        </div>
      </div>
    </div>

    <div style="width:480px">
      <div class="card">
        <div class="card-header"><h3 class="card-title">Current Registrations</h3></div>
        <div class="card-body">
          <table>
            <tr><th>ID</th><th>User</th><th>Course</th><th>When</th></tr>
            <?php while($r = $regs->fetch_assoc()): ?>
              <tr>
                <td><?php echo $r['id']; ?></td>
                <td><?php echo htmlspecialchars($r['user_email'] ?: ('#'.$r['user_id'])); ?></td>
                <td><?php echo htmlspecialchars($r['course_name'] ?: ('#'.$r['course_id'])); ?> <?php if(!empty($r['course_code'])) echo '('.htmlspecialchars($r['course_code']).')'; ?></td>
                <td><?php echo htmlspecialchars($r['registered_at']); ?></td>
              </tr>
            <?php endwhile; ?>
          </table>
        </div>
      </div>
    </div>
  </div>

<?php include 'footer.php';
