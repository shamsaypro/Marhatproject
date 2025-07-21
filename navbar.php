<?php include 'connection/db.php';
?>

<?php
// Handle clear all notifications
if(isset($_GET['clear_all']) && $_GET['clear_all'] == 'true') {
    $clear_query = "DELETE FROM notifications";
    if(mysqli_query($conn, $clear_query)) {
        // No redirect - just refresh the notifications
    } else {
        echo "Error clearing notifications: " . mysqli_error($conn);
    }
}
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm" style="padding: 5px;">
  <div class="container-fluid">
    <a class="navbar-brand" href="#"></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavDropdown"
      aria-controls="navbarNavDropdown" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarNavDropdown">
      <h3 class="mx-auto text-white" style="font-family: 'Times New Roman', serif;margin-right: 1000px">ONLINE RESULT MANAGEMENT SYSTEM</h3>

      <ul class="navbar-nav ms-auto">
        <?php if(isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
        <li class="nav-item dropdown position-relative me-3">
          <a class="nav-link position-relative" href="#" id="navbarDropdownMenuLink" role="button" data-bs-toggle="dropdown"
            aria-expanded="false">
            <i class="fas fa-bell fa-lg text-white"></i>
            <?php 
            $sql = "SELECT * FROM notifications";
            $res = mysqli_query($conn,$sql);
            $count = mysqli_num_rows($res);
            if($count > 0): ?>
            <span class="position-absolute badge rounded-pill bg-danger pulse notification-badge">
              <?php echo $count; ?>+
              <span class="visually-hidden">unread messages</span>
            </span>
            <?php endif; ?>
          </a>
          <ul class="dropdown-menu dropdown-menu-end mt-2 shadow-lg" aria-labelledby="navbarDropdownMenuLink" style="width: 380px; max-height: 400px; overflow-y: auto;background-color: white;">
            <li><h6 class="dropdown-header d-flex justify-content-between align-items-start">
              <span>Notifications</span>
              <?php 
              $sql = "SELECT * FROM notifications";
              $res = mysqli_query($conn,$sql);
              $count = mysqli_num_rows($res);
              if($count > 0): ?>
              <span class="badge bg-primary rounded-pill">
                <?php echo $count; ?> New
              </span>
              <?php endif; ?>
            </h6></li>
            <?php 
            $query = "SELECT first_name,last_name,message FROM notifications JOIN users ON notifications.user_id=users.id ORDER BY notifications.created_at desc";
            $result = mysqli_query($conn,$query);
            if(mysqli_num_rows($result) > 0) {
                while($row= mysqli_fetch_assoc($result)){
            ?>
            <li>
              <a class="dropdown-item d-flex align-items-start" href="#">
               
                <div>
                  <div style="color: black;font-weight: bolder">Teacher <?php echo $row['first_name']?> <?php echo $row['last_name']?> </div>
                  <small class="text-muted"><?php echo $row['message']?></small>
                  <div class="text-muted small">2 minutes ago</div>
                </div>
              </a>
            </li>
            
            <li><hr class="dropdown-divider my-1"></li>
            <?php }
            } else { ?>
            <li>
              <a class="dropdown-item d-flex align-items-start" href="#">
                <div>
                  <div style="color: black;font-weight: bolder">No notifications</div>
                  <small class="text-muted">You have no new notifications</small>
                </div>
              </a>
            </li>
            <?php } ?>
            
            <?php 
            $sql = "SELECT * FROM notifications";
            $res = mysqli_query($conn,$sql);
            $count = mysqli_num_rows($res);
            if($count > 0): ?>
            <li>
              <a class="dropdown-item text-center py-2 text-primary fw-semibold" href="?clear_all=true">
                <i class="fas fa-list me-2"></i>Clear all
              </a>
            </li>
            <?php endif; ?>
          </ul>
        </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<style>
  /* Pulse animation for notification badge */
  .pulse {
    animation: pulse 1.5s infinite;
  }
  
  @keyframes pulse {
    0% {
      transform: translate(-50%, -50%) scale(1);
      box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7);
    }
    70% {
      transform: translate(-50%, -50%) scale(1.05);
      box-shadow: 0 0 0 10px rgba(220, 53, 69, 0);
    }
    100% {
      transform: translate(-50%, -50%) scale(1);
      box-shadow: 0 0 0 0 rgba(220, 53, 69, 0);
    }
  }
  
  /* Custom scrollbar for dropdown */
  .dropdown-menu::-webkit-scrollbar {
    width: 6px;
  }
  
  .dropdown-menu::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
  }
  
  .dropdown-menu::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 10px;
  }
  
  .dropdown-menu::-webkit-scrollbar-thumb:hover {
    background: #555;
  }
  
  /* Hover effects */
  .dropdown-item:hover {
    background-color: #f8f9fa;
  }

  /* Kurekebisha sehemu ya badge ili ikae ndani ya kengele */
  .notification-badge {
    top: 50%; /* Weka katikati kiwima */
    left: 50%; /* Weka katikati kimlalo */
    transform: translate(-50%, -50%); /* Rekebisha nafasi kutokana na ukubwa wake */
    padding: 0.2em 0.5em; /* Ongeza padding kidogo ili namba ikae vizuri */
    font-size: 0.75em; /* Punguza ukubwa wa font kidogo */
  }
</style>