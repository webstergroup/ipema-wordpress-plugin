<?php
ini_set('display_errors', true);
set_time_limit(0);
while (ob_end_clean()) {}

//header('Connection: close');
ignore_user_abort(true);

/*ob_start();

$output = plugins_url('progress.txt', __FILE__);

print '<p>Running import in background</p>';
print "<p>Check <a href=\"$output\">progress.txt</a> regularly to see status of import</p>";

$size = ob_get_length();
header("Content-Length: $size");

ob_end_flush();
flush();
session_write_close();*/

print 'Importing...<br>';
flush();

unlink(__DIR__ . '/progress.txt');
while (file_exists(__DIR__ . '/do_loop'))
{
    file_put_contents(
        __DIR__ . '/progress.txt',
        date('Y-m-d H:i:s') . "\n",
        FILE_APPEND
    );
    sleep(30);
    print 'looped<br>';
    flush();
}

file_put_contents(
    __DIR__ . '/progress.txt',
    'Ended: ' . date('Y-m-d H:i:s') . "\n",
    FILE_APPEND
);
die('Done');
