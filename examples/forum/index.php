<?php
  include '../../src/dispatcher.php';
  $sess = ApiInterfaceDispatcher::requestKeyAndToken();
?><!doctype html>
<html lang="en">
  <head>
  	<meta charset="utf-8">
  	<title>ApiInterface Example - Forum</title>
    <script src="../../src/api_interface.js"></script>
    <script src="http://files.aiham.net/js/jquery-1.6.4.min.js"></script>
    <script>
      (function () {
        var execute_ready = false,

          escape = function (text) {
            var e = document.createElement('p');
            e.appendChild(document.createTextNode(text));
            return e.innerHTML;
          },

          formatPostHtml = function (date, name, message) {
            return '<div class="post"><div class="details">' +
              '<span class="date">' + escape(date) + '</span> ' +
              '<span class="name">' + escape(name) + '</span>' +
              '</div><p class="message">' + escape(message) + '</p></div>';
          },

          timeToDateString = function (time) {
            var date = new Date(parseInt(time + '', 10) * 1000),
              hours = date.getHours(),
              ampm = 'am';

            if (hours > 12) {
              hours -= 12;
              ampm = 'pm';
            }

            return hours + ':' + date.getMinutes() + ampm + ' ' +
              date.getDate() + '.' + date.getMonth() + '.' + date.getFullYear();
          },

          ready = function () {
            var $posts, $status, $name, $message, $submit,
              posts_controller, last_post, ignore_post_ids = [];

            // Only execute on the second ready() call
            if (!execute_ready) {
              execute_ready = true;
              return;
            }

            $posts = $('#posts');
            $status = $('#status');
            posts_controller = new api.controllers.Posts();

            posts_controller.getPosts({}, function (results) {
              var posts_html = 'No posts',
                l = results.length,
                i;

              if (l > 0) {
                posts_html = '';

                for (i = 0; i < l; i++) {
                  posts_html = formatPostHtml(
                    timeToDateString(results[i]['time']),
                    results[i]['name'],
                    results[i]['message']
                  ) + posts_html;
                }

                last_post = parseInt(results[l - 1]['time'] + '', 10);
              }

              $posts.html(posts_html);

              $('#add_post').append($('<form>').submit(function (e) {
                e.preventDefault();

                posts_controller.addNewPost(
                  {name: $name.val(), message: $message.val()},
                  function (result) {
                    $posts.html(formatPostHtml(
                      timeToDateString(result['time']),
                      result['name'],
                      result['message']
                    ) + $posts.html());

                    ignore_post_ids.push(result['uuid']);

                    $status.text('Post successful');

                    window.setTimeout(function () {
                      $status.text('');
                      $submit.removeAttr('disabled');
                    }, 2000);
                  },
                  function (status, message) {
                    $status.text('Post failed (' + status + '): ' + message);

                    window.setTimeout(function () {
                      $status.text('');
                      $submit.removeAttr('disabled');
                    }, 2000);
                  }
                );

                $status.text('Loading...');
                $name.val('');
                $message.val('');
                $submit.attr('disabled', 'disabled');
              }).html(
                '<div><label for="name">Name</label> <input type="text" id="name"></div>' +
                '<div><label for="message">Message</label> <textarea id="message"></textarea></div>' +
                '<div><input type="submit" id="submit" value="Post"></div>'
              ));

              $name = $('#name');
              $message = $('#message');
              $submit = $('#submit');

              $status.text('');

              // Check for new posts every 15 seconds
              window.setInterval(function () {
                posts_controller.getPosts(
                  {time: last_post},
                  function (results) {
                    var posts_html, i, l = results.length, change = false;

                    last_post = (new Date()).getTime() / 1000;

                    if (l > 0) {
                      posts_html = $posts.html();

                      for (i = 0; i < l; i++) {
                        if (ignore_post_ids.indexOf(results[i]['uuid']) >= 0) {
                          continue;
                        }
                        change = true;
                        posts_html = formatPostHtml(
                          timeToDateString(results[i]['time']),
                          results[i]['name'],
                          results[i]['message']
                        ) + posts_html;
                      }

                      if (change) {
                        $posts.html(posts_html);
                      }
                    }
                  }
                );
              }, 15000);
            });
          };

        var api = new ApiInterface('api.php', <?php echo json_encode($sess['key']) . ', ' . json_encode($sess['token']); ?>);
  
        api.ready(ready);

        $(window.document).ready(ready);
  
      }());
    </script>
  </head>
  <body>
  	<div id="status">Loading...</div>
  	<div id="add_post"></div>
  	<div id="posts"></div>
  </body>
</html>
