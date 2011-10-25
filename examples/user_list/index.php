<?php
  include 'custom_dispatcher.php';
  $sess = CustomDispatcher::requestKeyAndToken();
?><!doctype html>
<html lang="en">
  <head>
  	<meta charset="utf-8">
  	<title>ApiInterface Example - User List</title>
    <script src="../../src/api_interface.js"></script>
    <script>
      var addUser;
  
      (function () {
  
        var users, list, api, updateUsers;

        list = [];

        api = new ApiInterface('api.php', <?php echo json_encode($sess['key']) . ', ' . json_encode($sess['token']); ?>);

        updateUsers = function () {
          var e = document.getElementById('users');
          e.innerHTML = '';
          e.appendChild(
            document.createTextNode(
              'Users: ' + (list.length < 1 ? 'none' : list.join(', '))
            )
          );
        };
  
        addUser = function () {
          var name = prompt('User name?', '');
          if (!name) {
            return;
          }
          users.addNewUser(
            {name: name},
            function (result) {
              list.push(name);
              updateUsers();
            },
            function (status, message) {
              alert('Could not add user (' + status + '): ' + message);
            }
          );
        };
  
        api.ready(function () {
          users = new api.controllers.Users();
  
          users.getAllUsers({}, function (results) {
            list = results;
            updateUsers();
          });
        });
  
      }());
    </script>
  </head>
  <body>
    <input type="submit" value="Add" onclick="addUser();">
  	<div id="users">Loading...</div>
  </body>
</html>
