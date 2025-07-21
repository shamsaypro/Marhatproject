<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<div class="admin-sidebar">
    <ul class="nav flex-column">

        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <!-- Dashboard -->
            <li class="nav-item">
                <a class="nav-link active" href="admin-dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                </a>
            </li>

            <!-- User Management -->
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-users-cog me-2"></i> User Management
                </a>
                <ul class="dropdown-menu" aria-labelledby="userDropdown">
                    <li><a class="dropdown-item" href="register.php"><i class="fas fa-user-plus me-2"></i> Add New User</a></li>
                    <li><a class="dropdown-item" href="students.php"><i class="fas fa-users me-2"></i> Add students</a></li>
                </ul>
            </li>

            <!-- Results Management -->
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="resultsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-poll me-2"></i> Results Management
                </a>
                <ul class="dropdown-menu" aria-labelledby="resultsDropdown">
                    <li><a class="dropdown-item" href="totalres.php"><i class="fas fa-eye me-2"></i> View marks</a></li>
                    <li><a class="dropdown-item" href="viewreport.php"><i class="fas fa-upload me-2"></i> Upload Results</a></li>
                </ul>
            </li>
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-bell me-2"></i> Send Notifications
                </a>
                <ul class="dropdown-menu" aria-labelledby="userDropdown">
                    <li><a class="dropdown-item" href="email_form.php"><i class="fas fa-user-plus me-2"></i> About Meeting</a></li>
                    <li><a class="dropdown-item" href="res_note.php"><i class="fas fa-user-plus me-2"></i> About result</a></li>



                </ul>
            
        </li>
         <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-users-cog me-2"></i> Registration
                </a>
                <ul class="dropdown-menu" aria-labelledby="userDropdown">
                    <li><a class="dropdown-item" href="class.php"><i class="fas fa-user-plus me-2"></i> Register class</a></li>
                    <li><a class="dropdown-item" href="subjects.php"><i class="fas fa-user-edit me-2"></i> Register subject</a></li>
                </ul>
            </li>
        

        <li class="nav-item">
            <a class="nav-link" href="report.php">
                <i class="fas fa-file-alt me-2"></i> Reports
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="grade.php">
                <i class="fas fa-file-alt me-2"></i> Grade system
            </a>
        </li>
        <?php elseif(isset($_SESSION['role']) && $_SESSION['role'] === 'teacher'):?>
            <li class="nav-item">
                <a class="nav-link active" href="teacherdash.php">
                    <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                </a>
            </li>
             <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="resultsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-poll me-2"></i> Results Management
                </a>
                <ul class="dropdown-menu" aria-labelledby="resultsDropdown">
                    <li><a class="dropdown-item" href="result.php"><i class="fas fa-list me-2"></i> Record marks</a></li>
                </ul>
            </li>
    
         <?php endif?>
        
             <li class="nav-item">
                <a class="nav-link" href="setting.php">
                    <i class="fas fa-cog me-2"></i> Setting
                </a>
            </li>
        <li class="nav-item">
            <a class="nav-link" href="logout.php">
                <i class="fas fa-sign-out-alt me-2"></i> Logout
            </a>
        </li>
    </ul>
</div>
