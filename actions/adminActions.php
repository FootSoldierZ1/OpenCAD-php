<?php
    /**
    Open source CAD system for RolePlaying Communities. 
    Copyright (C) 2017 Shane Gill

    This program is free software: you can redistribute it and/or modify
        it under the terms of the GNU General Public License as published by
        the Free Software Foundation, either version 3 of the License, or
        (at your option) any later version.

    This program comes with ABSOLUTELY NO WARRANTY; Use at your own risk.
    **/
    /*
        This file handles all actions for admin.php script
    */

    $iniContents = parse_ini_file("../properties/config.ini", true); //Gather from config.ini file
    $connectionsFileLocation = $_SERVER["DOCUMENT_ROOT"]."/openCad/".$iniContents['main']['connection_file_location'];

    require($connectionsFileLocation);

    /* Handle POST requests */
    if (isset($_POST['approveUser'])){ 
	    approveUser();
    }
    if (isset($_POST['rejectUser'])){ 
	    rejectUser();
    }

    /* FUNCTIONS */
    /* Gets the user count. Returns value */
    function getUserCount()
    {
        $link = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
	
        if (!$link) { 
            die('Could not connect: ' .mysql_error());
        }
        
        $query = "SELECT COUNT(*) from users";

        $result=mysqli_query($link, $query);
        $row = mysqli_fetch_array($result, MYSQLI_BOTH);

        mysqli_close($link);

        return $row[0];        
    }

    function getPendingUsers()
    {
       $link = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
	
        if (!$link) { 
            die('Could not connect: ' .mysql_error());
        }
        
        $query = "SELECT id, name, email, identifier FROM users WHERE approved = '0'";

        $result=mysqli_query($link, $query);

        $num_rows = $result->num_rows;

        if($num_rows == 0)
        {
            echo "<div class=\"alert alert-info\"><span>There are currently no access requests</span></div>";
        }
        else
        {
            echo '
               <table id="pendingUsers" class="table table-striped table-bordered">
                <thead>
                 <tr>
                  <th>Name</th>
                  <th>Email</th>
                  <th>Identifier</th>
                  <th>Groups</th>
                  <th>Actions</th>
                 </tr>
                </thead>
                <tbody>           
            ';

            while($row = mysqli_fetch_array($result, MYSQLI_BOTH))
            {
               echo '
                <tr>
                    <td>'.$row[1].'</td>
                    <td>'.$row[2].'</td>
                    <td>'.$row[3].'</td>
                    <td>';
                    
                    getUserGroups($row[0]);
                    
                    echo ' </td>
                    <td>
                     <form action="../actions/adminActions.php" method="post">
                       <input name="approveUser" type="submit" class="btn btn-xs btn-link" value="Approve" />
                       <input name="rejectUser" type="submit" class="btn btn-xs btn-link" value="Reject" />
                       <input name="uid" type="hidden" value='.$row[0].' />
                     </form>                    
                    </td>
                </tr>
               ';
            }

            echo '
                </tbody>
               </table>
            ';
        }
    }

    function getUserGroups($uid)
    {
        $link = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
	
        if (!$link) { 
            die('Could not connect: ' .mysql_error());
        }
        
        $sql = "SELECT departments.department_name FROM user_departments_temp INNER JOIN departments on user_departments_temp.department_id=departments.department_id WHERE user_departments_temp.user_id = \"$uid\"";

		
	    $result1 = mysqli_query($link, $sql);
        
        while($row1 = mysqli_fetch_array($result1, MYSQLI_BOTH))
        {
              echo $row1[0]."<br/>";
        }
    }


    function approveUser()
    {
        $uid = $_POST['uid'];
        
        /* If a user has been approved, the following needs to be done:
            1. Insert user's groups from temp table to regular table
            2. Set user's approved status to 1
        */

        /* Copy from temp table to regular table */
        $link = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
	
        if (!$link) {
            die('Could not connect: ' .mysql_error());
        }

        //Insert into user_departments
        $query = "INSERT INTO user_departments SELECT u.* FROM user_departments_temp u WHERE user_id = ?";
        
        try {
            $stmt = mysqli_prepare($link, $query);
            mysqli_stmt_bind_param($stmt, "i", $uid);
            $result = mysqli_stmt_execute($stmt);
            
            if ($result == FALSE) {
                die(mysqli_error($link));
            }
        }
        catch (Exception $e)
        {
            die("Failed to run query: " . $e->getMessage());
        }

        /* Delete from user_departments_temp */
        $query = "DELETE FROM user_departments_temp WHERE user_id = ?";
        
        try {
            $stmt = mysqli_prepare($link, $query);
            mysqli_stmt_bind_param($stmt, "i", $uid);
            $result = mysqli_stmt_execute($stmt);
            
            if ($result == FALSE) {
                die(mysqli_error($link));
            }
        }
        catch (Exception $e)
        {
            die("Failed to run query: " . $e->getMessage());
        }

        /* Set user's approved status */
        $query = "UPDATE users SET approved = '1' WHERE id = ?";
	
        try {
            $stmt = mysqli_prepare($link, $query);
            mysqli_stmt_bind_param($stmt, "i", $uid);
            $result = mysqli_stmt_execute($stmt);
            
            if ($result == FALSE) {
                die(mysqli_error($link));
            }
        }
        catch (Exception $e)
        {
            die("Failed to run query: " . $e->getMessage());
        }

        mysqli_close($link);

        session_start();
        $_SESSION['accessMessage'] = '<div class="alert alert-success"><span>Successfully approved user access</span></div>';
        
        sleep(1);//seconds to wait..
        header("Location:../administration/admin.php");
        

    }

    function rejectUser()
    {
        /* If a user has been rejected, the following needs to be done:
            1. Delete user's group's from user_departments_temp table
            2. Delete user's profile from users table
        */
        $uid = $_POST['uid'];
        
        /* Delete groups from temp table */
        $link = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
	
        if (!$link) {
            die('Could not connect: ' .mysql_error());
        }

        $query = "DELETE FROM user_departments_temp where user_id = ?";
	
        try {
            $stmt = mysqli_prepare($link, $query);
            mysqli_stmt_bind_param($stmt, "i", $uid);
            $result = mysqli_stmt_execute($stmt);
            
            if ($result == FALSE) {
                die(mysqli_error($link));
            }
        }
        catch (Exception $e)
        {
            die("Failed to run query: " . $e->getMessage());
        }
        

        /* Delete user from user table */
        
        $query = "DELETE FROM users where id = ?";
	
        try {
            $stmt = mysqli_prepare($link, $query);
            mysqli_stmt_bind_param($stmt, "i", $uid);
            $result = mysqli_stmt_execute($stmt);
            
            if ($result == FALSE) {
                die(mysqli_error($link));
            }
        }
        catch (Exception $e)
        {
            die("Failed to run query: " . $e->getMessage());
        }

        mysqli_close($link);

        session_start();
        $_SESSION['accessMessage'] = '<div class="alert alert-danger"><span>Successfully rejected user access</span></div>';
        
        sleep(1);//seconds to wait..
        header("Location:../administration/admin.php");
        
    }

    function getGroupCount($gid)
    {
        $link = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
	
        if (!$link) { 
            die('Could not connect: ' .mysql_error());
        }
        
        $query = "SELECT COUNT(*) from user_departments WHERE department_id = \"$gid\"";

        $result=mysqli_query($link, $query);
        $row = mysqli_fetch_array($result, MYSQLI_BOTH);

        mysqli_close($link);

        return $row[0];
    }

    
?>